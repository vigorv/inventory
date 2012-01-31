#
# FreeGet Downloader module
# (c) IT Deluxe, 2007
# (c) Dmitry Root, 2007
#

package FreeGet::Downloader;

use DBI;

$DOWNLOADER = {};

$ST_QUEUE = 0;
$ST_STARTED = 1;
$ST_REJECTED = 2;
$ST_DOWNLOAD = 3;
$ST_DERROR = 4;
$ST_NOFREESPACE = 5;
$ST_ZIP = 6;
$ST_ZERROR = 7;
$ST_FTP = 8;
$ST_FERROR = 9;
# other status codes are not for this server.

$MP_NONE = 0;
$MP_GOTTASK = 10;
$MP_DOWNLOAD = 20;
$MP_ZIP_START = 40;
$MP_ZIP_END = 50;
# other manipulation codes are not for this server.

sub new {
    my($proto,$name,$config) = @_;
    my $class = ref($proto) || $proto;
    my $self = { name => $name, config => $config };
    bless($self,$class);
    $DOWNLOADER = $self;
    return $self;
}

sub LoadModules {
    my $self = shift;
    my $path = $self->{config}->{path}->{modules};
    my $dh;
    opendir($dh, "$path/FG_Download") || die "Could not open FG_Download directory: $!";
    my @files = readdir($dh);
    closedir($dh);
    $self->{modules} = [];
    foreach my $i(sort @files) {
        next unless $i =~ /\.pm$/;
        require "$path/FG_Download/$i";
        my $mod = $i;
        $mod =~ s/\.pm$//;
        $mod =~ s/^\d+_+//;
        my $module = "FG_Download::$mod"->new($self->{name},$self->{config});
        push(@{$self->{modules}}, $module);
    }
}

sub SetStatus {
    my $self = shift;
    my($url, $status) = @_;
    $url += 0; $status += 0;
    $self->{dbh}->do("update `urls` set `status`=$status where `id`=$url;") || die "SQL error: ${DBI::errstr}";
}

sub Log {
    my $self = shift;
    my($url,$mp,$comment) = @_;
    $comment = $self->{dbh}->quote($comment);
    $url += 0; $mp += 0;
    if($self->{lastlog} == $mp) {
        $self->{dbh}->do("update `log` set `show`=0, `time`=current_timestamp, `comment`=$comment where id=$self->{lastlog_id};") || die "SQL error: ${DBI::errstr}";
    } else {
        $self->{dbh}->do("insert into `log` (`url`,`manipulation`,`time`,`comment`) values ($url,$mp,current_timestamp,$comment);") || die "SQL error: ${DBI::errstr}";
        $self->{lastlog} = $mp;
        my($id) = $self->{dbh}->selectrow_array("select `id` from `log` where `url`=$url and `manipulation`=$mp order by `id` desc limit 1;");
        die "SQL error: ${DBI::errstr}" unless $id += 0;
        $self->{lastlog_id} = $id;
    }
}

sub SetTitle {
    my $self = shift;
    my($state,$url,$percent) = @_;
    if($state eq 'download') {
        $0 = "freegetd: $self->{name} - $state [$percent\%] - $url";
    } elsif($state ne '') {
        $0 = "freegetd: $self->{name} - $state - $url";
    } else {
        $0 = "freegetd: $self->{name}";
    }
}

sub NeedZip {
    my $self = shift;
    my($filename) = @_;
    return 1 if -d $filename; # Always zip if it's a directory
    my @st = stat($filename);
    my $size = @st[7] + 0;
    return 0 if $size < $self->{config}->{zip}->{minsize}+0 || $size > $self->{config}->{zip}->{maxsize}+0;
    return 1 if $self->{config}->{zip}->{leave} eq '';
    my @ext = split(/[\s\t]+/, $self->{config}->{zip}->{leave});
    my $re = '(?:' . join(')|(?:', @ext) . ')';
    return 0 if $filename =~ /\.$re$/;
    return 1;
}

sub execute {
    my $cmd = shift;
    my $fd;
    my $pid = open($fd, "$cmd >/dev/null 2>&1 |");
    my $line = <$fd>;
    close($fd);
    return $line eq '' ? 1 : 0;
}

sub Start {
    my $self = shift;
    $SIG{HUP} = sub { $DOWNLOADER->{HUP} = 1; };
    $SIG{TERM} = sub { $DOWNLOADER->{TERM} = 1; };
    $SIG{ABRT} = sub { $DOWNLOADER->{ABRT} = 1; };
    $SIG{INT} = sub {};

    $self->LoadModules();

    my $config = $self->{config};
    my $name = $self->{name};
    my $dbh = $self->{dbh} = DBI->connect("DBI:mysql:database=$config->{mysql}->{name};host=$config->{mysql}->{host}", $config->{mysql}->{user}, $config->{mysql}->{pass});
    die "Could not connect to DB: ${DBI::errstr}" unless $dbh;
    my $workdir = "$config->{path}->{tmp}/freegetd.$name";
    execute("mkdir -p $workdir") || die "Could not create work directory";
    chdir($workdir) || die "Could not change current dir to work directory: $!";

    while(1) {
        $self->SetTitle('');
        my $data = $dbh->selectall_arrayref($config->{$name}->{query});
        die "SQL error: ${DBI::errstr}" unless $data;
        if($self->{HUP} || $self->{TERM}) {
            last;
        }
        if(@{$data} == 0) {
            sleep($config->{global}->{sleep});
            next;
        }
        foreach my $row(@{$data}) {
            $self->{lastlog} = 0;
            $self->SetTitle('');
            execute("rm -rf $workdir/*") || die "Could not clean work directory";
            if($self->{HUP} || $self->{TERM}) {
                last;
            }
            $self->{HUP} = $self->{TERM} = $self->{ABRT} = 0;
            # NOTE: Here is the needed output format for SQL query in configfile
            my($id, $url, $limit, $recursive, $rec_depth, $login, $pass) = @{$row};
            $self->{id} = $id;
            $self->{url} = $url;
            $self->{limit} = $limit;
            $self->{recursive} = $recursive;
            $self->{rec_depth} = $rec_depth;
            $self->{login} = $login;
            $self->{pass} = $pass;
            $self->SetStatus($id, $ST_STARTED);
            $self->Log($id, $MP_GOTTASK);
            $dbh->do("update `urls` set `pid`=$$ where `id`=$id;") || die "SQL error: ${DBI::errstr}";

            my $module;
            my $found = 0;
            foreach my $i(@{$self->{modules}}) {
                my $tmp = $i->RightURL($url);
                if($tmp eq '') {
                    $found = 1;
                    $module = $i;
                    last;
                }
                $url = $tmp;
            }
            if(!$found) {
                # URL syntax error
                $self->SetStatus($id, $ST_REJECTED);
                next;
            }

            $self->SetStatus($id, $ST_DOWNLOAD);
            my $filename = $module->Download({ url => $url, cb_arg => $self, cb_proc => \&DownloadCallback, recursive => $recursive, rec_depth => $rec_depth, login => $login, password => $pass});
            if($filename eq '') {
                if($self->{TERM} || $self->{HUP}) {
                    last;
                } elsif($self->{ABRT}) {
                    $self->{ABRT} = 0;
                    next;
                }
                if($self->{nofreespace}) {
                    $self->SetStatus($id, $ST_NOFREESPACE);
                    $self->{nofreespace} = 0;
                } else {
                    $self->SetStatus($id, $ST_DERROR);
                }
                next;
            }
            my $t = time;
            my $outdir = $config->{path}->{output};
            my $outfile = $name . '_' . $t . '_' . $filename;
            if($self->NeedZip($filename)) {
                $self->SetStatus($id, $ST_ZIP);
                $self->Log($id, $MP_ZIP_START);
                my $level = $config->{zip}->{level} + 0;
                $outfile .= '.zip';
                $self->SetTitle('zip', $url);
                my $rc = execute("$config->{zip}->{command} -$level -r -m '$outdir/$outfile' '$filename' >/dev/null");
                unless($rc) {
                    $self->SetStatus($id, $ST_ZERROR);
                    next;
                }
                $self->Log($id, $MP_ZIP_END);
                $filename .= '.zip';
            } else {
                unless(execute("mv -f '$filename' '$outdir/$outfile'")) {
                    $self->SetStatus($id, $ST_FERROR);
                }
            }
            $self->SetStatus($id, $ST_FTP);
            my $qname = $dbh->quote($outfile);
            $dbh->do("update `urls` set `name`=$qname where `id`=$id;") || die "SQL error: ${DBI::errstr}";
            if($self->{TERM} || $self->{HUP}) {
                last;
            }
        }
    }

    chdir("/");
    execute("rm -rf $workdir");
    $dbh->disconnect();
}

sub DownloadCallback {
    my $self = shift;
    my($state, $size, $fullsize) = @_;
    if($state eq 'fullsize') {
        $fullsize += 0;
        my $fs_kb = $fullsize / 1024;
        $self->{dbh}->do("update `urls` set `filesize`=$fs_kb where `id`=$self->{id};") || die "SQL error: ${DBI::errstr}";
        if($fullsize > $self->{limit}) {
            $self->{nofreespace} = 1;
            return 0;
        }
        if(!$fullsize) {
            $self->Log($self->{id}, $MP_DOWNLOAD, 'unknown');
        } else {
            $self->Log($self->{id}, $MP_DOWNLOAD, '0%');
            $self->{lastp} = 0;
        }
    } elsif($state eq 'download') {
        if(!$fullsize && $size > $self->{limit}) {
            $self->{nofreespace} = 1;
            return 0;
        }
        if($fullsize) {
            my $p = int($size * 100 / $fullsize);
            $self->SetTitle('download', $self->{url}, $p);
            if($p >= 10 && $self->{lastp} < 10) { $self->Log($self->{id}, $MP_DOWNLOAD, '10%'); $self->{lastp} = 10; }
            if($p >= 30 && $self->{lastp} < 30) { $self->Log($self->{id}, $MP_DOWNLOAD, '30%'); $self->{lastp} = 30; }
            if($p >= 50 && $self->{lastp} < 50) { $self->Log($self->{id}, $MP_DOWNLOAD, '50%'); $self->{lastp} = 50; }
            if($p >= 70 && $self->{lastp} < 70) { $self->Log($self->{id}, $MP_DOWNLOAD, '70%'); $self->{lastp} = 70; }
            if($p >= 90 && $self->{lastp} < 90) { $self->Log($self->{id}, $MP_DOWNLOAD, '90%'); $self->{lastp} = 90; }
        }
    } elsif($state eq 'done') {
        $self->Log($self->{id}, $MP_DOWNLOAD, 'done');
    }

    return 0 if($self->{TERM} || $self->{HUP} || $self->{ABRT});
    return 1;
}


1;

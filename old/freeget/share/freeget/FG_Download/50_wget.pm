#
# FreeGet wget downloading module
# (c) IT Deluxe, 2007
# (c) Dmitry Root, 2007
#

package FG_Download::wget;

use LWP::UserAgent;

sub new {
    my($proto,$name,$config) = @_;
    my $class = ref($proto) || $proto;
    my $self = { name => $name, config => $config };
    bless($self, $class);
    return $self;
}

sub RightURL {
    my $self = shift;
    my($url) = @_;
                 #    prfx              ://   user:pass@                  www.any.com     :1234     /somewhere/path
    return '' if($url =~ /^(?:ftp|http|https)\:\/\/(?:[\w\d\-_\.\%]*\:[^\@]*\@)?[\w\d\-_\.]+(?:\:\d+)?(?:\/.*)?$/i);
    return $url;
}

sub shell_quote {
    my @rc = ();
    foreach my $i(@_) {
        $i =~ s/([\$\`\"\\\n])/\\$1/g;
        push(@rc,$i);
    }
    return @rc;
}

sub Download {
    my $self = shift;
    my($opts) = @_;

    my $url = $opts->{url};
    my @args = ('--progress=dot:mega');
    $ENV{LC_ALL} = 'C';
    $ENV{LANG} = $ENV{LANGUAGE} = '';

    if($opts->{login}) {
        my($ql,$qp) = shell_quote($opts->{login}, $opts->{password});
        push(@args, "\"--user=$ql\"");
        push(@args, "\"--password=$qp\"");
    }
    my $recursive = $opts->{recursive};
    my $rec_depth = $opts->{rec_depth} + 0;
    my $ftp = ($url =~ /^ftp/);
    if($recursive) {
        push(@args, '-r');
        push(@args, "--level=$rec_depth");
        unless($ftp) {
            push(@args, '-E');
            push(@args, '-k');
            push(@args, '-p');
        }
    }
    unless($ftp) {
        my($host) = $url =~ m/^http(?:s)?\:\/\/([\w\d\-_\.]+)/i;
        $host =~ s/^www\.//;
        if($host && defined($self->{config}->{"wget/cookies/$host"})) {
            push(@args, '--no-cookies');
            foreach my $i(keys %{$self->{config}->{"wget/cookies/$host"}}) {
                my($name,$val) = shell_quote($i, $self->{config}->{"wget/cookies/$host"}->{$i});
                push(@args, "--header=\"Cookie: $name=$val\"");
            }
        }
    }
    my($qurl) = shell_quote($url);
    push(@args, '"'.$qurl.'"');

    my $wget_out;
    my $wget = open($wget_out, "$self->{config}->{wget}->{command} ".join(' ',@args)." 2>&1 |");
    return '' unless defined($wget);

    my $fullsize = 0;
    my $size = 0;
    my $size_base = 0;
    my $res;
    while(my $line = <$wget_out>) {
        chomp($line);
        my $rc = 1;
        if($line =~ /^Length: ([\d\s\,]+)/i) {
            unless($recursive) {
                $fullsize = $1;
                $fullsize =~ s/[\s\,]//g;
                $rc = &{$opts->{cb_proc}}($opts->{cb_arg}, 'fullsize', 0, $fullsize);
            }
        } elsif($line =~ /^\s*\d+\w\s+([\.\s]*)/) {
            my $dots = $1;
            $dots =~ s/\s//g;
            my $dotc = length($dots);
            $size += 64*1024*$dotc;
            $rc = &{$opts->{cb_proc}}($opts->{cb_arg}, 'download', $size_base + $size, $fullsize);
        } elsif($line =~ /\`(.*?)\'\s+saved\s+\[(\d+)/i) {
            my($fname,$fsize) = ($1,$2);
            $fsize += 0;
            if($recursive) {
                $size_base += $fsize if($fname !~ /\.listing$/);
                ($res) = $fname =~ m/^([^\/]+)/;
            } else {
                $size_base = $fsize;
                ($res) = $fname =~ m/([^\/]+)$/;
            }
            $size = 0;
        }
        unless($rc) {
            kill(2, $pid);
            return '';
        }
    }
    close($wget_out);
    if($recursive && !$ftp && -d $res) {
        $self->{root} = $res;
        $self->{current} = $url;
        $self->{counter} = 1;
        if($self->{current} =~ /\?/ || ($self->{current} =~ /\/([^\/]+)$/ && $1 =~ /\./)) {
            $self->{current} =~ s/\?.*//;
            $self->{current} =~ s/\/([^\/]*)$//;
        }
        ($self->{base}) = $url =~ m/^((?:http|https)\:\/\/(?:[\w\d\-_\.\%]*\:[^\@]*\@)?[\w\d\-_\.]+(?:\:\d+)?)/i;
        $self->ScanResults($res, '', '');
        $self->ScanResults("$res/_media_", '/_media_', '../') if -d "$res/_media_";
        delete($self->{load_cache});
    }
    my $rc = &{$opts->{cb_proc}}($opts->{cb_arg}, 'done', $size_base, $size_base);
    return '' unless($rc);
    return $res;
}

sub ReadFile {
    my $self = shift;
    my($fn) = @_;
    my $fd;
    open($fd, $fn) || return undef;
    my $data = join('',<$fd>);
    close($fd);
    return $data;
}

sub WriteFile {
    my $self = shift;
    my($fn,$data) = @_;
    my $fd;
    open($fd, ">$fn") || return 0;
    print $fd $data;
    close($fd);
    return 1;
}

sub ScanResults {
    my $self = shift;
    my($dir, $path, $top) = @_;
    my $dh;
    opendir($dh, $dir) || return 0;
    my @files = readdir($dh);
    closedir($dh);
    foreach my $i(@files) {
        next if $i =~ /^\./;
        if(-d "$dir/$i") {
            return 0 unless $self->ScanResults("$dir/$i", "$path/$i", "$top../");
        } elsif($i =~ /\.htm(?:l)?$/i) {
            my $content = $self->ReadFile("$dir/$i");
            return 0 unless defined($content);
            $content = $self->ReplaceHTML($content, $dir, $path, $top);
            return 0 unless defined($content);
            $self->WriteFile("$dir/$i", $content) || return 0;
        } elsif($i =~ /\.css$/i) {
            my $content = $self->ReadFile("$dir/$i");
            return 0 unless defined($content);
            $content = $self->ReplaceCSS($content, $dir, $path, $top);
            return 0 unless defined($content);
            $self->WriteFile("$dir/$i", $content) || return 0;
        }
    }
    return 1;
}

sub LoadMedia {
    my $self = shift;
    my($addr,$path) = @_;
    if($addr !~ /^http/) {
        if($addr =~ /^\//) {
            $addr = $self->{base} . $addr;
        } else {
            $addr = $self->{base} . $path . '/' . $addr;
        }
    }
    return $self->{load_cache}->{$addr} if $self->{load_cache}->{$addr};
    mkdir("$self->{root}/_media_");
    my $ua = LWP::UserAgent->new();
    $ua->env_proxy();
    $ua->timeout(10);
    my $resp = $ua->get($addr);
    return '' unless $resp->is_success;
    my $ct = $resp->header('Content-Type');
    $ct =~ s/\;.*$//;
    $ct =~ s/^[\s\t\r\n]+|[\s\t\r\n]+$//g;
    my $types = {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/bmp' => 'bmp',
        'text/html' => 'html',
        'image/gif' => 'gif',
        'text/x-javascript' => 'js',
        'text/css' => 'css',
        'application/x-shockwave-flash' => 'swf'
    };
    my $ext = $types->{$ct};
    unless($ext) {
        ($ext) = $addr =~ m/\.([\w\d]+)$/;
        return '' unless $ext;
    }
    my $fd;
    my $c = $self->{counter}++;
    open($fd, ">$self->{root}/_media_/$c.$ext") || return '';
    binmode($fd);
    print $fd $resp->content;
    close($fd);
    $self->{load_cache}->{$addr} = "$c.$ext";
    return "$c.$ext";
}

sub ReplaceTag {
    my $self = shift;
    my($type,$tag,$dir,$path,$top) = @_;
    return $tag unless $tag =~ /$type\=([\"\']?)([^\s]+)\1/i;
    my $src = $2;
    return $tag if -r "$dir/$src";
    my $fn = $self->LoadMedia($src, $path);
    return $tag if $fn eq '';
    my $t = $top || '.';
    $tag =~ s/$type\=([\"\']?)([^\s]+)\1/$type="$t\/_media_\/$fn"/;
    return $tag;
}

sub ReplaceHTML {
    my $self = shift;
    my($content,$dir,$path,$top) = @_;

    # Do we really need comments?
    $content =~ s/\<\!\-\-.*?\-\-\>//g;

    # Replace some tags...
    $content =~ s/(\<img.*?\>)/$self->ReplaceTag('src',$1,$dir,$path,$top)/gei;
    $content =~ s/(\<script.*?\>)/$self->ReplaceTag('src',$1,$dir,$path,$top)/gei;
    $content =~ s/(\<link.*?\>)/$self->ReplaceTag('href',$1,$dir,$path,$top)/gei;
    $content =~ s/(\<embed.*?\>)/$self->ReplaceTag('src',$1,$dir,$path,$top)/gei;

    # Replace CSS entries...
    $content =~ s/\<([^\>]*?)style\=([\"\'])(.*?)\2([^\>]*?)\>/"<$1 style=\"" . $self->ReplaceCSS($3,$dir,$path,$top) . "\" $4>"/gei;

    # Replace <style>'s ...
    $content =~ s/\<style(.*?)\>(.*?)\<\/style\>/"<style $1>" . $self->ReplaceCSS($2,$dir,$path,$top) . "<\/style>"/gei;

    return $content;
}

sub ReplaceCSS {
    my $self = shift;
    my($content,$dir,$path,$top) = @_;
    $content =~ s/\/\*.*?\*\///g;
    my $t = $top || '.';
    $content =~ s/url\(\s*([\'\"]?)(.*?)\1\s*\)/"url($t\/_media_\/".$self->LoadMedia($2,$path).')'/gei;
    return $content;
}

1;


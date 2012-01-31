#
# FreeGet module for rapidshare.com parsing
# (c) IT Deluxe, 2007
# (c) Dmitry Root, 2007
#

package FG_Download::rapidshare_com;

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
    return $url unless $url =~ /^http\:\/\/(?:www\.)?rapidshare.com\/files\/\d+\//i;
    
    my $ua = LWP::UserAgent->new;
    $ua->timeout(10);
    $ua->env_proxy;
    my @cookies;
    while(my($k,$v) = each %{$self->{config}->{'wget/cookies/rapidshare.com'}}) {
        push(@cookies, "$k=$v");
    }
    my $cookies = join('; ', @cookies);
    $ua->default_header( 'Cookie' => $cookies );
    
    my $rs_typesel = $ua->get($url);
    return '' unless $rs_typesel->is_success;
    my $typesel = $rs_typesel->content;
    
    # Look for correct address to jump from here
    return '' unless $typesel =~ /\<form[^\>]+action\=\"(.*?)\"/i;
    my $rs_premium = $ua->post($1, { 'dl.start' => 'PREMIUM' });
    return '' unless $rs_premium->is_success;
    my $premium = $rs_premium->content;
    
    # Look for source with minimum bandwidth used
    my $source = '';
    my $rank = 1;
    while($premium =~ s/\<tr\>\<td\>\<a\s+href\=\"(.*?)\"\>[^\<]+\<\/a\>\<\/td\>\<td\>\<font\s+color\=\"\w+\"\>(\d+)\<\/font\>\s*\/\s*(\d+)[^\<]+\<\/td\>\<\/tr\>//i) {
        my($src_url,$src_used,$src_band) = ($1,$2,$3);
        $src_used += 0;
        $src_band += 0;
        next unless $src_band > 0;
        my $src_rank = $src_used / $src_band;
        if($rank > $src_rank) {
            $source = $src_url;
            $rank = $src_rank;
        }
    }
    return '' unless $source;
    
    # set correct cookies
    my($host) = $source =~ m/^http\:\/\/([\w\d\.\-_]+)/i;
    %{$self->{config}->{"wget/cookies/$host"}} = %{$self->{config}->{'wget/cookies/rapidshare.com'}};
    return $source;
}

sub Download {
    my $self = shift;
    my($opts) = @_;
    
    return '';
}

1;


INSTALL

1) Install clamav
    $ make
    $ make install
2) Edit config of clamav to this
    /usr/local/etc/clamd.conf

    LogFile /var/log/clamav/clamd.log
    LogTime
    LogVerbose
    PidFile /var/run/clamav/clamd.pid
    TemporaryDirectory /tmp
    DatabaseDirectory /var/lib/clamav
    LocalSocket /var/run/clamav/clamd
    FixStaleSocket
    MaxConnectionQueueLength 30
    User clamav
    AllowSupplementaryGroups
    ScanArchive
    ArchiveMaxRecursion 2
    ScanRAR
    ArchiveMaxFileSize 50M
    ArchiveMaxFiles 15000
    
3) Set clamav to autostart
    /etc/rc.conf
    
    clamav_clamd_enable="YES"
    clamav_freshclam_enable="YES"
    clamav_milter_enable="YES"

4) tar xjvf proftpd-1.3.0.tar.bz2

5) gzip -cd proftpd-1.3.0-rus.patch.gz | patch -p0

6) Start configure of proftpd
    $ ./configure --enable-openssl --with-modules=mod_tls:mod_sql:mod_sql_mysql:mod_ifsession:mod_quotatab:mod_quotatab_sql:mod_ratio:mod_readme:mod_rewrite:mod_site_misc:mod_wrap:mod_codeconv:mod_clamav --with-includes=/usr/local/include/mysql:/usr/local/include --with-libraries=/usr/local/lib/mysql --enable-ctrls

7) make
when you see error "/usr/bin/ld: cannot find -lnsl" go to file Make.rules, find param -lnsl and delete this
when you see error  "undefined reference to 'libiconv_close'" go to Make.rules where you deleted -lnsl and put them full path to libiconv.so, example /usr/local/lib/libiconv.so

8) make install 

9) create rc.d/proftpd.sh script

10) overwrite proftpd.conf for my conf-files such this proftpd.conf and dirrecotory proftpd (vhost,extra)

11) configure proftpd.conf files

12) install fx_cleaner script into /usr/local/bin and push it to /etc/crontab

13) reboot

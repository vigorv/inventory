--
-- FreeGet tables
-- (c) 2007, IT Deluxe
-- (c) 2007, Dmitry Root
--

--
-- mysql
--

create table `users` (
    `id` int not null auto_increment primary key,
    `login` varchar(64) not null,
    `priority` int not null default 0,
    `quota` int not null,
    `client` int not null,
    `free` int not null
) default charset=utf8;

create table `urls` (
    `id` int not null auto_increment primary key,
    `url` text not null,
    `name` text not null default '',
    `pid` int default 0,
    `login` text,
    `password` text,
    `recursive` int default 0,
    `rec_length` int default 5,
    `owner` int not null,
    `status` int not null default 0,
    `filesize` int not null default 0
) default charset=utf8;

create table `log` (
    `url` int not null,
    `manipulation` int not null default 0,
    `time` timestamp,
    `show` int default 0,
    `comment` varchar(32)
) default charset=utf8;

create table `abort` (
    `id` int not null auto_increment primary key,
    `url` int not null
) default charset=utf8;


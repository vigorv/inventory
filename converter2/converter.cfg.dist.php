<?php
$neededFuncs = array('mb_substr', 'preg_match', 'mysql_connect');
foreach ($neededFuncs as $f)
	if (!function_exists($f))
		die('function "' . $f . '" not defined. Plz fix PHP configuration.' . "\n\n");
if (!defined("_PARTNER_"))
{
	die("Error. Not executable. (Try [_partner_name_].php)\n\n");
}

$dbs = array(
	"mycloud" => array(
		"host"	=> "localhost",
		"name"	=> "tushkan",
		"user"	=> "root",
		"pwd"	=> "hengfhjkm",
		"locale"=> "utf8",
	),
	"videoxq" => array(
		"host"	=> "localhost",
		"name"	=> "videoxq",
		"user"	=> "root",
		"pwd"	=> "hengfhjkm",
		"locale"=> "utf8",
	),
	"fastlink" => array(
		"host"	=> "flux3.anka.ws",
		"name"	=> "fastlink",
		"user"	=> "root",
		"pwd"	=> "vig2orv115",
		"locale"=> "utf8",
	),
	"media1" => array(
		"host"	=> "db.anka.ws",
		"name"	=> "lms",
		"user"	=> "migration",
		"pwd"	=> "1q2w3e4r",
		"locale"=> "cp1251",
	)
);
//DEFINE("_SL_",			chr(92));		//СИМВОЛ КОСОЙ ЧЕРТЫ
DEFINE("_SL_",			"/");		//СИМВОЛ КОСОЙ ЧЕРТЫ

//КЛЮЧИ ДЛЯ FFMPEG
DEFINE("_FFMPEG_KEYS_",	    '-deinterlace -r 29.97 -vcodec libx264 -flags +loop -cmp +chroma -deblockalpha 0 -deblockbeta 0 -crf 21 -bt 256k -refs 3 -coder 0 -me_method full -me_range 16 -subq 5 -partitions +parti4x4+parti8x8+partp8x8 -g 90 -keyint_min 25 -level 30 -trellis 2 -sc_threshold 40 -i_qfactor 0.71 -acodec libfaac -ab 112kb -ar 48000 -ac 2 -alang rus');
//DEFINE("_FFMPEG_KEYS_",	'-deinterlace -r 29.97 -vcodec libx264 -flags +loop -cmp +chroma -deblockalpha 0 -deblockbeta 0 -crf 21 -bt 256k -refs 3 -coder 0 -me_method full -me_range 16 -subq 5 -partitions +parti4x4+parti8x8+partp8x8 -g 90 -keyint_min 25 -level 30 -trellis 2 -sc_threshold 40 -i_qfactor 0.71 -acodec libfaac -ab 112kb -ar 48000 -ac 2 -map 0:0 -map 0:1 -map 0:2 -acodec libfaac -ab 112kb -ar 48000 -ac 2');//РАБОЧИЙ
//DEFINE("_FFMPEG_KEYS_",	'-deinterlace -r 29.97 -vcodec libx264 -flags +loop -cmp +chroma -deblockalpha 0 -deblockbeta 0 -crf 21 -bt 256k -refs 3 -coder 0 -me_method full -me_range 16 -subq 5 -partitions +parti4x4+parti8x8+partp8x8 -g 90 -keyint_min 25 -level 30 -trellis 2 -sc_threshold 40 -i_qfactor 0.71');
//КЛЮЧИ ДЛЯ MPBOX
DEFINE("_MP4BOX_KEYS_",	"");

DEFINE("_MP4BOX_MAX_SIZE_",	2 * 1048550); //МАКС РАЗМЕР ФАЙЛА ДЛЯ РАЗДЕЛЕНИЯ НА ЧАСТИ В КИЛОБАЙТАХ
//DEFINE("_MP4BOX_MAX_SIZE_",	2 * 1024 * 5); //МАКС РАЗМЕР ФАЙЛА ДЛЯ РАЗДЕЛЕНИЯ НА ЧАСТИ В КИЛОБАЙТАХ

DEFINE("_FFMPEG_",		_SL_ . "tools" . _SL_ . "ffmpeg-0.5" . _SL_ . "ffmpeg.exe");	//путь к утилите ffmpeg
DEFINE("_MP4BOX_",		"MP4Box");		//путь к утилите mp4box
DEFINE("_MP4TAGS_",		"/usr/local/bin/mp4tags");		//путь к утилите mp4tags
DEFINE("_MP4ART_",		"/usr/local/bin/mp4art");		//путь к утилите mp4art
DEFINE("_ATOMICP_",		_SL_ . "tools" . _SL_ . "atomicparsley" . _SL_ . "atomicparsley.exe");		//путь к утилите mp4tags


DEFINE("_AUDIO_TRACKS_LIMIT_",	"7");	//максимально поддерживаемое кол-во аудио дорожек в видеофайле
DEFINE("_QUEUE_CONDITION_",	"");	//дополнительное условие к выборке очереди
//DEFINE("_CONDITION_",	"");	//дополнительное условие к выборке списка фильмов для конвертации
DEFINE("_THREADS_CNT_",	1);	//Кол-во потоков
DEFINE("_QUEUE_LIMIT_",	10);	//Кол-во записей в выборке новой очереди

DEFINE("_STATION_",		);	//УНИКАЛЬНЫЙ Номер рабочей станции. Сломано специально чтобы не пропустить при настройке

DEFINE("_CATALOGURL_",	"http://media1.itd");

DEFINE("_MYCLOUD_SITE_", "http://myicloud.ws");

//DEFINE("_ROOT_PATH_",	_SL_ . "home" . _SL_ . "converter" . _SL_ );
//DEFINE("_CONSOLE_CHARSET_", '866');			//DOS КОДИРОВКА КОНСОЛИ

DEFINE("_ROOT_PATH_",	_SL_ . "home" . _SL_ . "converter2" . _SL_ );
DEFINE("_CONSOLE_CHARSET_", 'utf-8');			//UTF8 КОДИРОВКА КОНСОЛИ

DEFINE("_SOURCE_CHARSET_", 'utf-8');			//UTF8 КОДИРОВКА КОНСОЛИ

DEFINE("_SRC_PATH_",	"/mnt/catalog/catalog");	//путь к оригинальным файлам (aka блэйз)
DEFINE("_SRC2_PATH_",	"/mnt/typhoon/ftp/trash");	//путь к оригинальным файлам (aka тайфун)
DEFINE("_MEDIA_PATH_",	"/mnt/catalog/x/catalog");	//путь к файлам в медиакаталоге (aka медиа1)

DEFINE("_POSTER_PATH_",	_ROOT_PATH_ . "posters");		//путь к файлам постеров
DEFINE("_POSTER_SRC_",	"/mnt/media1");		//путь к файлам постеров

DEFINE("_COPY_PATH_",	_ROOT_PATH_ . "content" . _SL_ . _PARTNER_ . _SL_ . "in");		//путь для копий файлов
DEFINE("_CONV_PATH_",	_ROOT_PATH_ . "content" . _SL_ . _PARTNER_ . _SL_ . "out");	//путь сконвертированных файлов
DEFINE("_READY_PATH_",	_ROOT_PATH_ . "content" . _SL_ . _PARTNER_ . _SL_ . "ready");	//путь до оригинальных файлов, прошедших обработку (сюда происходит перемещение файлов из директории "in")
DEFINE("_CMD_PATH_",	_ROOT_PATH_ . "cmd");		//путь к командным файлам
DEFINE("_TMP_PATH_",	_ROOT_PATH_ . "tmp");		//путь к директории временных файлов
DEFINE("_LOG_PATH_",	_ROOT_PATH_ . "log");		//путь к log-файлам

DEFINE("_CMD_TODO_",	0);		//фильм не обрабатывался
DEFINE("_CMD_COPY_",	1);		//проверка наличия копий файлов фильма (копирование на конвертер)
DEFINE("_CMD_CONV_",	2);		//операция конвертирования
DEFINE("_CMD_MODIFY_",	3);		//разбивка/склейка файлов на части, внесение метаинфо
DEFINE("_CMD_CLOUDUP_",	4);		//выгрузка файла(ов) с компрессора в облачное хранилище
DEFINE("_CMD_PARTNERUP_",	5);	//выгрузка файла(ов) с облачного хранилища партнеру
DEFINE("_CMD_SAVEBACK_",	6);	//сохранение инфо о файле в БД сайта партнера
DEFINE("_CMD_ADD_",		7);		//добавление объекта в витрины
DEFINE("_CMD_UNIVERSE_",8);		//добавление объекта в ПП

DEFINE("_CMD_DONE_",	50);	//фильм обработан

DEFINE("_STATE_WAIT_",		0);	//ожидание очереди
DEFINE("_STATE_PROCESS_",	1);	//операция в процессе выполнения
DEFINE("_STATE_OK_",		2);	//операция выполнена
DEFINE("_STATE_ERR_",		10);//ощмбка. операция не выполнена

DEFINE("_CLOUD_STATE_SPIRIT_",		1);//состояние файла - тень отца гамлета (можно удалить)
DEFINE("_CLOUD_STATE_ACTUAL_",		2);//состояние файла - актуальный файл

DEFINE("_COMPLETED_CMD_",	_CMD_TODO_);//КОД ОПЕРАЦИИ КОНВЕРТИРОВАНИЯ НА КОТОРОЙ ЗАКОНЧИЛИ (ДЛЯ ЗАДАНИЯ ЦИКЛА НЕ С НАЧАЛА)

include ('classes' . _SL_ . 'iconvertertransport.php');//ПОДКЛЮЧАЕМ ИНТЕРФЕЙС ТРАНСПОРТА
include ('classes' . _SL_ . _PARTNER_ . _SL_ . 'transport.php');
include ('converter.php');
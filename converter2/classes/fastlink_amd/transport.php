<?php
/**
 * класс обеспечивающий связь с файловыми серверами партнера,
 * с серверами БД партнера
 *
 * этот класс обеспечивает взаимодействие с ресурсами сайта videoxq.com
 *
 */
class partnerTransport implements iConverterTransport
{
	/**
	 * храним конфиги подключений ко всем оперируемым БД
	 *
	 * @var mixed
	 */
	protected $dbs;

	public $errorNo;
	public $errorMsg;

	public function copyFiles($files)
	{
		$cmds = array();
		if (!empty($files))
		{
			foreach ($files as $f)
			{
				if (file_exists(_SRC_PATH_ . $f))
					$cmds[] = 'rsync -r --size-only ' . _SRC_PATH_ . $f . ' ' . _COPY_PATH_ . $f . ' 2>&1';
			}
		}
		return $cmds;
	}

	public function copyPosters($posters)
	{
        return array();
	}

	/**
	 * сгенерировать команду копирования сконвертированного файла
	 * в хранилище партнера
	 *
	 * может вернуть пустую команду, если партнер решает не копировать файл по своему усмотрению
	 *
	 * @param string $oldName = оригинальное имя файла
	 * @param string $newName - готовый файл (полный путь)
	 * @param string $subDir - субдиректория (для разного качества конвертирования)
	 * @return string
	 */
	public function copyOutCmd($oldName, $newName, $subDir)
	{
		$cmd = array();
		$this->errorMsg = '';
		$oldInfo = pathinfo($oldName);
		$newDir = _OUT_PATH_ . $oldInfo['dirname'] . '/' . $subDir;
//echo $newDir . "\n";
//КОПИРУЕМ НА ШАРУ В СТРУКТУРЕ КАК НА БЛЕЗЕ (НА БЛЕЙЗ СИНХРОНИЗАЦИЯ ПРОИСХОДИТ ОТДЕЛЬНО)
		if ($this->createTree(_OUT_PATH_, $newDir))
			$cmd[] = "rsync -r --delete-after --size-only " . $newName . " " . $newDir . '/' . basename($newName);
		return $cmd;
	}


	/**
	 * обновление информации о старом файле и внесение информации о новом (сконвертированном)
	 *
	 * @param integer $originalId
	 * @param string $oldName
	 * @param string $newName
	 * @param string $preset
	 * @param mixed $fInfo - инфо  нового файла (размер в байтах, md5-хэш итд
	 */
	public function saveBack($originalId, $oldName, $newName, $preset, $fInfo)
	{
        return true;
	}

	public function getObjectToQueue($originalId, $originalVariantId = 0)
	{
		$condition = 'f.id = ' . $originalId;
		if (!empty($originalVariantId))
		{
			$condition .= ' AND fv.id = ' . $originalVariantId;
		}
		return $this->createQueue($condition);
	}

	/**
	 * получить массив данных о необработанных фильмах
	 *
	 * формат возвращаемой структуры
	 * 					$info['original_id'];
	 * 					$info['group_id'] - идентификатор группировки
	 * 					$info['just_online'];
						$info['md5s'] = array(хэши md5, соответствующих файлов)
						$info['files'] = array(
							относительный путь к I-му файлу объекта в примонтированной директории
							относительный путь к II-му файлу объекта в примонтированной директории
							...
						);
						$info['tags'] = array(
							"title"				=> title			- название объекта
							"title_original"	=> title_original	- оригинальное название объекта
							"genres"			=> genres			- жанры через запятую
							"countries"			=> countries		- страны через запятую
							"description"		=> description		- описание объекта
							"year"				=> year				- год
							"poster"			=> poster			- относительный путь к файлу постера
						);
	 * @return mixed
	 */
	public function createQueue($condition = '')
	{
$condition = 'f.id = 50000';//ДЛЯ ОТЛАДКИ
		if (empty($condition))
		{
			$limit = 'LIMIT ' . _QUEUE_LIMIT_;
		}
		else
		{
			$limit = '';
			$condition = ' AND ' . $condition;
		}
		$queue = array();
		$cfgName = 'fastlink_amd';
		$this->errorMsg = '';
		$db = mysql_connect($this->dbs[$cfgName]['host'], $this->dbs[$cfgName]['user'], $this->dbs[$cfgName]['pwd'], true);
		if (!$db)
		{
			$this->errorMsg = 'Невозможно соединиться с БД ' . $this->dbs[$cfgName]['host'] . '@' . $this->dbs[$cfgName]['user'];
			return false;
		}
		mysql_select_db($this->dbs[$cfgName]['name'], $db);
		mysql_query('SET NAMES ' . $this->dbs[$cfgName]['locale'], $db);

		$sql = '
			SELECT f.id, f.title, f.name AS file_name, f.chk_md5 AS md5, `f`.`group` AS amd_id FROM fl_catalog AS f
			WHERE `f`.`group` > 0 AND f.sgroup = 2 AND f.cloud_compressor IN (0, ' . _STATION_ . ') ' . $condition . ' ORDER BY f.id ' . $limit . '
		';
//				INNER JOIN film_genres ON (film_genres.film_id = f.id)
//				INNER JOIN genres as g ON (g.id = film_genres.genre_id)
		$q = mysql_query($sql, $db);
		$currentId = 0; $flRecords = array();
		while ($r = mysql_fetch_assoc($q))
		{
			if (strpos($r['file_name'], '270/') !== false)//ВЕРСИЮ ДЛЯ МОБИЛЬНЫХ ГЕНЕРИМ ПО НОВОЙ
			{
				$sql = 'UPDATE fl_catalog SET cloud_compressor = ' . _STATION_ . ', cloud_state = 1 WHERE id = ' . $r['id'];
				mysql_query($sql, $db);
				continue;
			}

			$sql = 'UPDATE fl_catalog SET cloud_compressor = ' . _STATION_ . ' WHERE id = ' . $r['id'];
			mysql_query($sql, $db);

			$flRecords[] = $r;
		}
		mysql_free_result($q);
		mysql_close($db);

        //var_dump($queue);

		return $queue;
	}

	public function __construct($dbs)
	{
		$this->dbs = $dbs;
	}

	/**
	 * метод проверки связи с серверами БД и файловыми серверами
	 *
	 */
	public function checkConnections()
	{
//ПРОВЕРЯЕМ КОННЕКТЫ С ФАЙЛОВЫМИ СЕРВЕРАМИ
		$errorMsg = '';
		$paths = array(
			_POSTER_PATH_,
			_COPY_PATH_,
			_OUT_PATH_,
			_SRC_PATH_
		);
		foreach ($paths as $p)
		{
			if (!file_exists($p))
			{
				$errorMsg = 'невозможно обратиться к файловому серверу (путь ' . $p . ')';
				return $errorMsg;
			}
		}

//ПРОВЕРЯЕМ КОННЕКТЫ С БД
		foreach ($this->dbs as $d)
		{
			$db = mysql_connect($d['host'], $d['user'], $d['pwd'], true);
			if (!$db)
			{
				$errorMsg = 'ошибка соединения с БД ' . $d['host'] . '@' . $d['user'];
				return $errorMsg;
			}
			mysql_select_db($d['name'], $db);
			mysql_close($db);
		}
		return $errorMsg;
	}

	/**
	 * создать дерево каталогов или проверить существуют ли все подкаталоги дерева
	 *
	 * @param string $path
	 * @return boolean
	 */
	function createTree($baseDir, $path)
	{
		$result = file_exists($path);
		if ($result) return $result;

		$root = explode($baseDir, $path);
		if (count($root) > 1)
		{
			$dirs = explode('/', $root[1]);
			$curDir = $baseDir;
			foreach ($dirs as $d)
			{
				if (empty($d)) continue;
				$curDir .= '/' . $d;
				$result = (file_exists($curDir) && (is_writable($curDir)));
				if (!$result)
				{
					$result = mkdir($curDir, 0755);
				}
				if (!$result)
				{
					$this->errorMsg = 'Невозможно создать директорию ' . $curDir;
					break;
				}
			}
		}
		return $result;
	}

	public function clearCache($info)
	{
        /*
		if (!empty($info['original_id']))
		{
			file_get_contents('http://videoxq.com/media/clearcache/' . $info['original_id']);
		}*/
	}
}
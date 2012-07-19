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
					$cmds[] = 'rsync -r --delete-after --size-only ' . _SRC_PATH_ . $f . ' ' . _COPY_PATH_ . $f . ' 2>&1';
			}
		}
		return $cmds;
	}

	public function copyPosters($posters)
	{
		$cmds = array();
		if (!empty($posters['poster']))
		{
			$p = $posters['poster'];
			$cmds[] = 'wget -O ' . _POSTER_PATH_ . $p . ' http://data2.videoxq.com/img/catalog' . $p . ' 2>&1';
			/*
			foreach ($posters['poster'] as $p)
			{
				$cmds[] = 'wget -O ' . _POSTER_PATH_ . $p . ' http://data2.videoxq.com/img/catalog' . $p . ' 2>&1';
			}
			*/
		}
		return $cmds;
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

	public function dropOriginal($originalId)
	{
		return true;
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
		$this->errorMsg = '';

		$cfgName = 'fastlink_vxq';
		$db = mysql_connect($this->dbs[$cfgName]['host'], $this->dbs[$cfgName]['user'], $this->dbs[$cfgName]['pwd'], true);
		if (!$db)
		{
			$this->errorMsg = 'Невозможно соединиться с БД ' . $this->dbs[$cfgName]['host'] . '@' . $this->dbs[$cfgName]['user'];
			return false;
		}
		mysql_select_db($this->dbs[$cfgName]['name'], $db);
		mysql_query('SET NAMES ' . $this->dbs[$cfgName]['locale'], $db);

		//ПРОВЕРЯЕМ СУЩЕСТВУЕТ ЛИ ФАЙЛ С ТЕКУЩИМ ИЛИ ОРИГИНАЛЬНЫМ КАЧЕСТВОМ
		$sql = 'SELECT * FROM fl_catalog WHERE id = ' . $originalId . ' AND (preset = "' . $preset . '" OR preset = "unknown")';
		$q = mysql_query($sql, $db);
		$variantInfo = mysql_fetch_assoc($q);
		mysql_free_result($q);

		if (empty($variantInfo))
		{
			$sql = 'SELECT * FROM fl_catalog WHERE id = ' . $originalId . ' ORDER BY id ASC';
			$q = mysql_query($sql, $db);
			$originalInfo = mysql_fetch_assoc($q);
			mysql_free_result($q);

			//ЗНАЧИТ НУЖНО СОЗДАТЬ ЗАПИСЬ ДЛЯ ФАЙЛА ДАННОГО КАЧЕСТВА
			$variantInfo = array(
				'user_id'		=> $originalInfo['user_id'],
				'email'			=> $originalInfo['email'],
				'title'			=> mysql_real_escape_string($originalInfo['title']),
				'original_name'	=> mysql_real_escape_string($originalInfo['original_name']),
				'name'			=> mysql_real_escape_string($preset . '/' . basename($newName)),
				'comment'		=> mysql_real_escape_string($originalInfo['comment']),
				'group'			=> $originalInfo['group'],
				'dt'			=> $originalInfo['dt'],
				'is_visible'	=> $originalInfo['is_visible'],
				'is_confirm'	=> $originalInfo['is_confirm'],
				'dir'			=> $originalInfo['dir'],
				'sgroup'		=> $originalInfo['sgroup'],
				'tp'			=> $originalInfo['tp'],
				'sz'			=> $fInfo['size'],
				'vtp'			=> $originalInfo['vtp'],
				'chk_md5'		=> $fInfo['md5'],

				'cloud_ready'		=> 1,
				'cloud_state'		=> _CLOUD_STATE_ACTUAL_,
				'cloud_compressor'	=> _STATION_,

				'preset'		=> $preset,
			);
			$sql = '
				INSERT INTO fl_catalog (id
					user_id, email, title, original_name, name, comment, group, dt, is_visible, is_confirm,
					dir, sgroup, tp, sz, vtp, chk_md5, cloud_ready, cloud_state, cloud_compressor,
					preset
				)
				VALUES (null, ' . $variantInfo['user_id'] . ', "' . $variantInfo['email'] . '",
					"' . $variantInfo['title'] . '", "' . $variantInfo['original_name'] . '",
					"' . $variantInfo['name'] . '", "' . $variantInfo['comment'] . '",
					' . $variantInfo['group'] . ', "' . $variantInfo['dt'] . '", ' . $variantInfo['is_visible'] . ',
					' . $variantInfo['is_confirm'] . ', "' . $variantInfo['dir'] . '", ' . $variantInfo['sgroup'] . ',
					' . $variantInfo['tp'] . ', ' . $variantInfo['sz'] . ', ' . $variantInfo['vtp'] . ',
					"' . $variantInfo['chk_md5'] . '", ' . $variantInfo['cloud_ready'] . ', ' . $variantInfo['cloud_state'] . ',
					' . $variantInfo['cloud_compressor'] . ', "' . $variantInfo['preset'] . '")';

			if (mysql_query($sql, $db))
			{
				$variantInfo['id'] = mysql_insert_id($db);
			}
			else
			{
				$this->errorMsg = 'Невозможно создать новый вариант файла';
				mysql_close($db);
				return false;
			}
		}
		else
		{
			//ОБНОВЛЯЕМ СУЩЕСТВУЮЩУЮ ЗАПИСЬ ФАЙЛА
			$sql = '
				UPDATE fl_catalog SET name="' . $variantInfo['name'] . '", sz = ' . $variantInfo['sz'] . ',
					chk_md5 = "' . $variantInfo['chk_md5'] . '", cloud_ready = ' . $variantInfo['cloud_ready'] . ',
					' . $variantInfo['cloud_state'] . ', "' . $preset . '"
				WHERE id = ' . $variantInfo['id'] . '
			';
			if (!mysql_query($sql, $db))
			{
				$this->errorMsg = 'Невозможно обновить данные файла (originalId=' . $variantInfo['id'] . ') SQL: ' . $sql;
				mysql_close($db);
				return false;
			}
		}

		$filesNames2Delete = array($variantInfo['original_name']);//ДЛЯ МЕДИА КАТАЛОГА
		$cfgName = 'media1';
		if (!empty($this->dbs[$cfgName]))
		{
			$db = mysql_connect($this->dbs[$cfgName]['host'], $this->dbs[$cfgName]['user'], $this->dbs[$cfgName]['pwd'], true);
			if (!$db)
			{
				$this->errorMsg = 'Невозможно соединиться с БД ' . $this->dbs[$cfgName]['host'] . '@' . $this->dbs[$cfgName]['user'];
				return false;
			}
			mysql_select_db($this->dbs[$cfgName]['name'], $db);
			mysql_query('SET NAMES ' . $this->dbs[$cfgName]['locale'], $db);

			//УДАЛЯЕМ ФАЙЛЫ
			$sql = 'DELETE FROM files WHERE FilmID = ' . $variantInfo['group'] . ' AND Name IN ("' . implode('","', $filesNames2Delete) . '")';
			$q = mysql_query($sql, $db);

			//ДОБАВЛЯЕМ ФАЙЛ В МЕДИАКАТАЛОГ
			$fileInfo = array(
				'Marked'	=> 0,
				'FilmID'	=> $variantInfo['group'],
				'Name'		=> basename($newName),
				'MD5'		=> $fInfo['md5'],
				'Path'		=> _MEDIA_PATH_ . $fInfo['path'] . '/' . $preset . '/' . basename($newName),
				'Size'		=> $fInfo['size'],
				'ed2kLink'	=> '',
				'dcppLink'	=> '',
				'dateadd'	=> time(),
				'isfilecheked'	=> 1,
				'tomoveback'	=> 0,
				'backpath'	=> '',
			);
			$sql = 'INSERT INTO files (ID, Marked, FilmID, Name, MD5, Path, Size, ed2kLink, dcppLink, dateadd, isfilecheked, tomoveback, backpath)
			VALUES (NULL, ' . $fileInfo['Marked'] . ', ' . $fileInfo['FilmID'] . ', "' . $fileInfo['Name'] . '", "' . $fileInfo['MD5'] . '", "' . $fileInfo['Path'] . '", ' . $fileInfo['Size'] . ', "' . $fileInfo['ed2kLink'] . '", "' . $fileInfo['dcppLink'] . '", "' . $fileInfo['dateadd'] . '", ' . $fileInfo['isfilecheked'] . ', ' . $fileInfo['tomoveback'] . ', "' . $fileInfo['backpath'] . '")
			';
			$q = mysql_query($sql, $db);
			mysql_close($db);
		}
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
$condition = 'f.id = 1524';//ДЛЯ ОТЛАДКИ
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
		$cfgName = 'fastlink_vxq';
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
			SELECT f.id, f.title, f.name AS file_name, f.chk_md5 AS md5, `f`.`group` AS vxq_id FROM fl_catalog AS f
			WHERE `f`.`group` > 0 AND f.sgroup = 1 AND f.cloud_compressor IN (0, ' . _STATION_ . ') ' . $condition . ' ORDER BY f.id ' . $limit . '
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

		$cfgName = 'videoxq';
		$this->errorMsg = '';
		$db = mysql_connect($this->dbs[$cfgName]['host'], $this->dbs[$cfgName]['user'], $this->dbs[$cfgName]['pwd'], true);
		if (!$db)
		{
			$this->errorMsg = 'Невозможно соединиться с БД ' . $this->dbs[$cfgName]['host'] . '@' . $this->dbs[$cfgName]['user'];
			return false;
		}
		mysql_select_db($this->dbs[$cfgName]['name'], $db);
		mysql_query('SET NAMES ' . $this->dbs[$cfgName]['locale'], $db);
		if (!empty($flRecords))
		{
		//ДОП ДАННЫЕ ВЫБИРАЕМ ИЗ БД ВХК
			foreach ($flRecords as $r)
			{
				$sql = 'select f.id, f.title, f.title_en, f.dir, f.description, f.year from films as f where f.id = ' . $r['vxq_id'];
				$q = mysql_query($sql, $db);
				$vr = mysql_fetch_assoc($q);
				mysql_free_result($q);

				if (!$vr) continue;

				if (empty($queue[$r['id']]))
				{
					$files = array();
					$md5s = array();
					$ovids = array();
				}

				$letter = strtolower(substr($vr['dir'], 0, 1));
				if (($letter >= '0') && ($letter <= '9'))
					$letter = '0-999';
				$files[] = "/" . $letter . "/" . $vr['dir'] . "/" . $r['file_name'];
				$md5s[] = $r['md5'];
				$ovids[] = 0;

				$tags = array(
					"title"				=> $vr['title'],
					"title_original"	=> $vr['title_en'],
					"description"		=> $vr['description'],
					"year"				=> $vr['year'],
				);
				$queue[$r['id']] = array(
					'original_id' => $r['id'],
					'vxq_id' => $vr['id'],//ид в бд ВХК
					'group_id' => $vr['id'],//ДЛЯ ВОЗМОЖНОЙ ГРУППИРОВКИ
					'just_online' => 0,
					'files' => $files,
					'md5s' => $md5s,
					'ovids' => $ovids,
					'tags' => $tags,
				);
			}
		}

		if (!empty($queue))
		{
			foreach ($queue as $k => $q)
			{
		//ОПРЕДЕЛЯЕМ СПИСОК ЖАНРОВ
				$sql = '
					SELECT g.title FROM genres AS g
						INNER JOIN films_genres AS fg ON (fg.genre_id = g.id)
					WHERE fg.film_id = ' . $q['vxq_id'] . '
				';
				$genres = array();
				$query = mysql_query($sql, $db);
				while ($r = mysql_fetch_assoc($query))
				{
					$genres[] = $r['title'];
				}
				mysql_free_result($query);
				$queue[$k]['tags']['genres'] = implode(', ', $genres);
		//ОПРЕДЕЛЯЕМ СПИСОК СТРАН
				$sql = '
					SELECT c.title FROM countries AS c
						INNER JOIN countries_films AS cf ON (cf.country_id = c.id)
					WHERE cf.film_id = ' . $q['vxq_id'] . '
				';
				$countries = array();
				$query = mysql_query($sql, $db);
				while ($r = mysql_fetch_assoc($query))
				{
					$countries[] = $r['title'];
				}
				mysql_free_result($query);
				$queue[$k]['tags']['countries'] = implode(', ', $countries);
		//ОПРЕДЕЛЯЕМ ПОСТЕР
				$sql = 'SELECT file_name, type FROM film_pictures WHERE film_id = ' . $q['vxq_id'];
				$genres = $smallPosters = $bigPosters = $posters = array();
				$query = mysql_query($sql);
				$subDir = '/img/catalog/';
				while ($p = mysql_fetch_array($query))
				{
					switch ($p['type'])
					{
						case "poster":
							$dir = _SL_ . 'posters';
							$posters[] = $subDir . $dir . _SL_ . basename($p['file_name']);
						break;
						case "smallposter":
							$dir = _SL_ . 'smallposters';
							$smallPosters[] = $subDir . $dir . _SL_ . basename($p['file_name']);
						break;
						case "bigposter":
							$dir = _SL_ . 'bigposters';
							$bigPosters[] = $subDir . $dir . _SL_ . basename($p['file_name']);
						break;
					}
				}
				mysql_free_result($query);
				if (empty($posters))
				{
					$posters = $smallPosters;
				}
				if (empty($posters))
				{
					$posters = $bigPosters;
				}
				$poster = '';
				if (!empty($posters))
				{
					foreach ($posters as $p)
					{
						$poster = $p;
						break;
					}
				}
				$queue[$k]['tags']['poster'] = $poster;
			}
		}
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
		if (!empty($info['original_id']))
		{
			file_get_contents('http://videoxq.com/media/clearcache/' . $info['original_id']);
		}
	}
}
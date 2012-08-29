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
		$newDir = _SRC_PATH_ . $oldInfo['dirname'] . '/' . $subDir;
//echo $newDir . "\n";
//КОПИРУЕМ НА БЛЭЙЗ
		if ($this->createTree(_SRC_PATH_, $newDir))
			$cmd[] = "rsync -r --delete-after --size-only " . $newName . " " . $newDir . '/' . basename($newName);
		$newDir = _SRC2_PATH_ . $oldInfo['dirname'] . '/' . $subDir;
//echo $newDir . "\n";
//КОПИРУЕМ НА ТАЙФУН
		if ($this->createTree(_SRC2_PATH_, $newDir))
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

		$cfgName = 'videoxq';
		$db = mysql_connect($this->dbs[$cfgName]['host'], $this->dbs[$cfgName]['user'], $this->dbs[$cfgName]['pwd'], true);
		if (!$db)
		{
			$this->errorMsg = 'Невозможно соединиться с БД ' . $this->dbs[$cfgName]['host'] . '@' . $this->dbs[$cfgName]['user'];
			return false;
		}
		mysql_select_db($this->dbs[$cfgName]['name'], $db);
		mysql_query('SET NAMES ' . $this->dbs[$cfgName]['locale'], $db);

		$sql = 'SELECT id FROM qualities WHERE title="' . $preset . '"';
		$q = mysql_query($sql, $db);
		$qualityInfo = mysql_fetch_assoc($q);
		mysql_free_result($q);

		if (empty($qualityInfo['id']))
		{
			$qualityInfo['id'] = 1;//НЕ УСТАНОВЛЕНО
		}

		//ПРОВЕРЯЕМ СУЩЕСТВУЕТ ЛИ ВАРИАНТ С ТЕКУЩИМ КАЧЕСТВОМ
		$sql = 'SELECT * FROM film_variants WHERE film_id = ' . $originalId . ' AND quality_id = ' . $qualityInfo['id'] . ' ORDER BY video_type_id ASC';
		$q = mysql_query($sql, $db);
		$variantInfo = mysql_fetch_assoc($q);
		mysql_free_result($q);

		if (empty($variantInfo))
		{
			$sql = 'SELECT * FROM film_variants WHERE film_id = ' . $originalId . ' ORDER BY id ASC';
			$q = mysql_query($sql, $db);
			$originalInfo = mysql_fetch_assoc($q);
			mysql_free_result($q);

			//ЗНАЧИТ НУЖНО СОЗДАТЬ ВАРИАНТ ДЛЯ ДАННОГО КАЧЕСТВА
			$variantInfo = array(
				'film_id'		=> $originalId,
				'video_type_id'	=> $originalInfo['video_type_id'],
				'resolution'	=> $fInfo['resolution'],
				'duration'		=> $originalInfo['duration'],
				'active'		=> 1,
				'created'		=> date('Y-m-d H:i:s'),
				'modified'		=> date('Y-m-d H:i:s'),
				'flag_catalog'	=> 0,
				'quality_id'	=> $qualityInfo['id'],
			);
			$sql = '
				INSERT INTO film_variants (id, film_id, video_type_id, resolution, duration, active, created, modified, flag_catalog, quality_id)
				VALUES (null, ' . $variantInfo['film_id'] . ', ' . $variantInfo['video_type_id']
			. ', "' . $variantInfo['resolution'] . '", "' . $variantInfo['duration'] . '", ' . $variantInfo['active']
			. ', "' . $variantInfo['created'] . '", "' . $variantInfo['modified'] . '", ' . $variantInfo['flag_catalog']
			. ', ' . $variantInfo['quality_id'] . ')
			';
			if (mysql_query($sql, $db))
			{
				$variantInfo['id'] = mysql_insert_id($db);
			}
			else
			{
				$this->errorMsg = 'Невозможно создать новый вариант фильма';
				mysql_close($db);
				return false;
			}
		}
		//ИЩЕМ СТАРЫЙ ФАЙЛ ЧТОБЫ ИЗМЕНИТЬ СТАТУС
		/**
		 * если старое и новое имя совпадает, выберем
		 * 		дибо в оригинальном качестве (0)
		 * 		либо текущем качестве
		 */
		$sql = 'SELECT ff.* FROM films AS f
			INNER JOIN film_variants AS fv ON (fv.film_id = f.id)
			INNER JOIN film_files as ff ON (ff.film_variant_id = fv.id)
			WHERE f.id = ' . $originalId . ' AND  fv.quality_id IN (0, ' . $qualityInfo['id'] . ') AND ff.file_name LIKE "%' . $oldName . '"
			ORDER BY fv.quality_id ASC
		';
		$q = mysql_query($sql, $db);
		$oldFileInfo = mysql_fetch_assoc($q);
		mysql_free_result($q);
		if ($oldFileInfo)
		{
			//ОТМЕЧАЕМ ФАЙЛ КАК ГОТОВЫЙ К УДАЛЕНИЮ
			$sql = 'UPDATE film_files SET cloud_ready = 0, cloud_state = ' . _CLOUD_STATE_SPIRIT_ . '
				WHERE id = ' . $oldFileInfo['id'];
			mysql_query($sql, $db);
			//ИЩЕМ ИНФО ПО СТАРОМУ ТРЭКУ (audio_info)
			$sql = 'SELECT * FROM tracks WHERE film_variant_id = ' . $oldFileInfo['film_variant_id'];
			$q = mysql_query($sql, $db);
			$oldTrackInfo = mysql_fetch_assoc($q);
			mysql_free_result($q);
		}

		if (empty($oldTrackInfo))
		{
			//ЕСЛИ СТАРОГО ФАЙЛА НЕТ (ЗНАЧИТ УЖЕ ЗАМЕНИЛИ ДРУГИМ КАЧЕСТВОМ)
			//НУЖНО СКОПИРОВАТЬ ИНФОРМАЦИЮ C ТРЭКF ВАРИАНТА ДРУГОГО КАЧЕСТВА
			$sql = 'SELECT t.* FROM films AS f
				INNER JOIN film_variants AS fv ON (f.id = fv.film_id)
				INNER JOIN tracks AS t ON (t.film_variant_id = fv.id)
			WHERE f.id = ' . $originalId . ' AND fv.id <> ' . $variantInfo['id'];
			$q = mysql_query($sql, $db);
			$oldTrackInfo = mysql_fetch_assoc($q);
			mysql_free_result($q);
		}

		if (!empty($oldTrackInfo))
		{
			//ПЕРЕСОХРАНЯЕМ НОВЫЙ ТРЭК К НОВОМУ ВАРИАНТУ
			$sql = 'SELECT id FROM tracks WHERE film_variant_id = ' . $variantInfo['id'];
			$q = mysql_query($sql, $db);
			$existsTrackInfo = mysql_fetch_assoc($q);
			mysql_free_result($q);
			if (!$existsTrackInfo)
			{
				if (empty($qualityInfo['audio']))
					$audioInfo = $oldTrackInfo['audio_info'];
				else
					$audioInfo = $qualityInfo['audio'];
				$sql = 'INSERT INTO tracks (id, film_variant_id, language_id, translation_id, audio_info)
				VALUES (NULL, ' . $variantInfo['id'] . ', ' . $oldTrackInfo['language_id'] . ', ' . $oldTrackInfo['translation_id'] . ', "' . $audioInfo . '")
				';
				mysql_query($sql, $db);
			}
		}

		//ТЕПЕРЬ ДОБАВЛЯЕМ НОВЫЙ ФАЙЛ

		//ПРОВЕРКА ДОБАВЛЯЛИ ИЛИ НЕТ
		$sql = 'SELECT ff.* FROM films AS f
			INNER JOIN film_variants AS fv ON (fv.film_id = f.id)
			INNER JOIN film_files as ff ON (ff.film_variant_id = fv.id)
			WHERE f.id = ' . $originalId . ' AND fv.quality_id = ' . $qualityInfo['id'] . ' AND ff.file_name = "' . ($preset . '/' . basename($newName)) . '"
		';
		$q = mysql_query($sql, $db);
		$fileInfo = mysql_fetch_assoc($q);
		mysql_free_result($q);
		if (!$fileInfo)
		{
			//ДОБАВЛЯЕМ
			$fileInfo = array(
				'film_variant_id' => $variantInfo['id'],
				'file_name' => $preset . '/' . basename($newName),
				'size' => $fInfo['size'],
				'md5' => $fInfo['md5'],
				'dcpp_link' => '',
				'ed2k_link' => '',
				'server_id' => 0,
				'is_lost' => 0,
				'cloud_ready' => 1,
				'cloud_state' => _CLOUD_STATE_ACTUAL_,
				'cloud_compressor' => _STATION_,
			);
			$sql = 'INSERT INTO film_files (id, film_variant_id, file_name, size, md5, dcpp_link, ed2k_link, server_id, is_lost, cloud_ready, cloud_state, cloud_compressor)
			VALUES (NULL, ' . $fileInfo['film_variant_id'] . ', "' . $fileInfo['file_name'] . '", ' . $fileInfo['size'] . ', "' . $fileInfo['md5'] . '", "' . $fileInfo['dcpp_link'] . '", "' . $fileInfo['ed2k_link'] . '", ' . $fileInfo['server_id'] . ', ' . $fileInfo['is_lost'] . ', ' . $fileInfo['cloud_ready'] . ', ' . $fileInfo['cloud_state'] . ', ' . $fileInfo['cloud_compressor'] . ')
			';
			mysql_query($sql, $db);
		}
		else
		{
			//ОБНОВИМ СТАТУС (НА СЛУЧАЙ, ЕСЛИ СТАРОЕ И НОВОЕ ИМЯ И КАЧЕСТВО СОВПАДАЮТ)
			$sql = 'UPDATE film_files SET cloud_ready = 1, cloud_state = ' . _CLOUD_STATE_ACTUAL_ . '
				WHERE id = ' . $fileInfo['id'];
			mysql_query($sql, $db);
		}

		$filesIds2Delete = array();
		$filesNames2Delete = array();//ДЛЯ МЕДИА КАТАЛОГА
		if ($oldFileInfo)
		{
			//ТЕПЕРЬ ВЫБИРАЕМ СТАРЫЕ ЗАПИСИ О ФАЙЛАХ (СТАРОГО ВАРИАНТА), КОТОРЫЕ МОЖНО УДАЛИТЬ
			$sql = 'SELECT ff.id, ff.file_name, ff.cloud_state FROM films AS f
				INNER JOIN film_variants AS fv ON (fv.film_id = f.id)
				INNER JOIN film_files as ff ON (ff.film_variant_id = fv.id)
				WHERE f.id = ' . $originalId . ' AND fv.id = ' . $oldFileInfo['film_variant_id'] . ' AND ff.cloud_state > 0
			';
			$q = mysql_query($sql, $db);
			while ($r = mysql_fetch_assoc($q))
			{
				$filesIds2Delete[] = $r['id'];
				if ($r['cloud_state'] == _CLOUD_STATE_SPIRIT_)
					$filesNames2Delete[] = basename($r['file_name']);
			}
			mysql_free_result($q);
			if ((count($filesIds2Delete) > 0) AND (count($filesIds2Delete) == count($filesNames2Delete)))
			{
				//ЕСЛИ НЕ ОСТАЛОСЬ НИ ОДНОГО НЕЗАМЕНЕННОГО ФАЙЛА, МОЖНО УДАЛЯТЬ
				//УДАЛЯЕМ ФАЙЛЫ
				$sql = 'DELETE FROM film_files WHERE id IN (' . implode(',', $filesIds2Delete) . ')';
				$q = mysql_query($sql, $db);
				//УДАЛЯЕМ ВАРИАНТ
				$sql = 'DELETE FROM film_variants WHERE id = ' . $oldFileInfo['film_variant_id'];
				$q = mysql_query($sql, $db);
				//УДАЛЯЕМ ТРЭК
				$sql = 'DELETE FROM tracks WHERE film_variant_id = ' . $oldFileInfo['film_variant_id'];
				$q = mysql_query($sql, $db);
			}
		}
		mysql_close($db);
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

		$sql = '
			SELECT f.id, f.title, f.title_en, f.dir, f.description, f.year, f.just_online, fv.id AS ovid, ff.file_name, ff.id AS ffid, ff.md5 FROM films AS f
				INNER JOIN film_variants as fv ON (fv.film_id = f.id)
				INNER JOIN film_files AS ff ON (ff.film_variant_id = fv.id)
			WHERE f.is_license=1 AND f.active > 0 AND ff.cloud_ready=0 AND ff.cloud_state=0 AND ff.cloud_compressor IN (0, ' . _STATION_ . ') ' . $condition . ' ORDER BY f.id ' . $limit . '
		';
//				INNER JOIN film_genres ON (film_genres.film_id = f.id)
//				INNER JOIN genres as g ON (g.id = film_genres.genre_id)
		$q = mysql_query($sql, $db);
		$currentId = 0;
		while ($r = mysql_fetch_assoc($q))
		{
			if (strpos($r['file_name'], '270/') !== false)//ВЕРСИЮ ДЛЯ МОБИЛЬНЫХ ГЕНЕРИМ ПО НОВОЙ
			{
				$sql = 'UPDATE film_files SET cloud_compressor = ' . _STATION_ . ', cloud_state = ' . _CLOUD_STATE_SPIRIT_ . ' WHERE id = ' . $r['ffid'];
				mysql_query($sql, $db);
				continue;
			}

			if (empty($queue[$r['id']]))
			{
				$files = array();
				$md5s = array();
				$ovids = array();
			}

			$letter = strtolower(substr($r['dir'], 0, 1));
			if (($letter >= '0') && ($letter <= '9'))
				$letter = '0-999';
			$files[] = "/" . $letter . "/" . $r['dir'] . "/" . $r['file_name'];
			$md5s[] = $r['md5'];
			$ovids[] = $r['ovid'];
			$sql = 'UPDATE film_files SET cloud_compressor = ' . _STATION_ . ', cloud_state = ' . _CLOUD_STATE_BUSY_ . ' WHERE id = ' . $r['ffid'];
			mysql_query($sql, $db);

			$tags = array(
				"title"				=> $r['title'],
				"title_original"	=> $r['title_en'],
				"description"		=> $r['description'],
				"year"				=> $r['year'],
			);

			$queue[$r['id']] = array(
				'original_id' => $r['id'],
				'just_online' => $r['just_online'],
				'files' => $files,
				'md5s' => $md5s,
				'ovids' => $ovids,
				'tags' => $tags,
			);
			if (count($files) > 1)
			{
				$queue[$r['id']]['group_id'] = $r['id'];
			}
		}
		mysql_free_result($q);

		if (!empty($queue))
		{
			foreach ($queue as $k => $q)
			{
		//ОПРЕДЕЛЯЕМ СПИСОК ЖАНРОВ
				$sql = '
					SELECT g.title FROM genres AS g
						INNER JOIN films_genres AS fg ON (fg.genre_id = g.id)
					WHERE fg.film_id = ' . $q['original_id'] . '
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
					WHERE cf.film_id = ' . $q['original_id'] . '
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
				$sql = 'SELECT file_name, type FROM film_pictures WHERE film_id = ' . $q['original_id'];
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
			_SRC2_PATH_,
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

	/**
	 * вызов действия обновления инфо сконвертированного объекта в БД media1
	 *
	 * @param integer $originalId
	 * @param string $oldName
	 * @param string $newName
	 * @param string $preset
	 * @param mixed $fInfo - инфо  нового файла (размер в байтах, md5-хэш итд
	 */
	public function updateMedia1($originalId, $oldName, $newName, $preset, $fInfo)
	{
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

			//УДАЛЯЕМ ФАЙЛ
			$sql = 'DELETE FROM files WHERE FilmID = ' . $originalId . ' AND Name LIKE "%' . $oldName . '")';
			$q = mysql_query($sql, $db);

			//ДОБАВЛЯЕМ ФАЙЛ В МЕДИАКАТАЛОГ
			$fileInfo = array(
				'Marked'	=> 0,
				'FilmID'	=> $originalId,
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
}
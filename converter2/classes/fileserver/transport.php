<?php
/**
 * класс обеспечивающий связь с файловыми серверами партнера,
 * с серверами БД партнера
 *
 * этот класс обеспечивает взаимодействие с файловыми серверами myicloud
 *
 */
class partnerTransport
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
		if ($this->createTree(_SRC_PATH_, $newDir))
			$cmd[] = "rsync -r --delete-after --size-only " . $newName . " " . $newDir . '/' . basename($newName);
		return $cmd;
	}

    /**
     * статическое описание пресетов
     *
     * !!ВНИМАНИЕ!! структура скопирована с модели CPresets проекта Myicloud
     *
     * @return mixed
     */
    public static function getPresets() {
    	$presets = array(
    		'unknown'	=> array('id' => 1, 'title' => 'unknown'),
    		'low'		=> array('id' => 2, 'title' => 'low'),
    		'medium'	=> array('id' => 3, 'title' => 'medium'),
    		'high'		=> array('id' => 4, 'title' => 'high'),
    		'ultra'		=> array('id' => 5, 'title' => 'ultra'),
    	);
    	return $presets;
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
		$cfgName = 'mycloud';
		$db = mysql_connect($this->dbs[$cfgName]['host'], $this->dbs[$cfgName]['user'], $this->dbs[$cfgName]['pwd'], true);
		if (!$db)
		{
			$this->errorMsg = 'Невозможно соединиться с БД ' . $this->dbs[$cfgName]['host'] . '@' . $this->dbs[$cfgName]['user'];
			return false;
		}
		mysql_select_db($this->dbs[$cfgName]['name'], $db);
		mysql_query('SET NAMES ' . $this->dbs[$cfgName]['locale'], $db);

		$presets = $this->getPresets();
		if (empty($presets[$preset]))
			$qualityInfo = $presets['unknown'];//НЕ УСТАНОВЛЕНО
		else
			$qualityInfo = $presets[$preset];

		//ВЫБИРАЕМ ИНФОРМАЦИЮ О НЕТИПИЗИРОВАННОМ ФАЙЛЕ
		$sql = '
			SELECT fv.id, fl.server_id, fl.state, fl.folder FROM dm_files_variants fv
				INNER JOIN dm_filelocations AS fl ON (fl.id = fv.id)
				WHERE fv.file_id = ' . $originalId . ' AND fv.preset_id=0 LIMIT 1
		';
		$q = mysql_query($sql, $db);
		$oldFileInfo = mysql_fetch_assoc($q);
		mysql_free_result($q);

		//ЗНАЧИТ НУЖНО СОЗДАТЬ ВАРИАНТ ДЛЯ ДАННОГО КАЧЕСТВА
		$variantInfo = array(
			'file_id'		=> $originalId,
			'preset_id'		=> $qualityInfo['id'],
			'fsize'			=> $fInfo['size'],
			'fmd5'			=> $fInfo['md5'],
		);
		$sql = '
			INSERT INTO dm_files_variants (id, file_id, preset_id, fsize, fmd5)
			VALUES (null, ' . $variantInfo['file_id'] . ', ' . $variantInfo['preset_id']
		. ', ' . $variantInfo['fsize'] . ', "' . $variantInfo['fmd5'] . '"'
		. ')';
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

		if ($oldFileInfo)
		{
			//ТЕПЕРЬ ДОБАВЛЯЕМ НОВУЮ ЛОКАЦИЮ
			$fileInfo = array(
				'id' => $variantInfo['id'],
				'server_id' => $oldFileInfo['server_id'], //$preset . '/' . basename($newName),
				'state'		=> $oldFileInfo['state'],
				'folder'	=> $oldFileInfo['folder'] . '/' . $preset . '/',
				'fsize'		=> $fInfo['size'],
				'fname'		=> basename($newName),
			);
			$sql = 'INSERT INTO dm_filelocations (id, server_id, state, fsize, fname, folder)
			VALUES (' . $fileInfo['id'] . ', ' . $fileInfo['server_id'] . ', ' . $fileInfo['state'] . ', '
			. $fileInfo['fsize'] . ', "' . $fileInfo['fname'] . '", "' . $fileInfo['folder'] . '")';
			mysql_query($sql, $db);
		}
		mysql_close($db);
	}

	public function dropOriginal($originalId)
	{
		$this->errorMsg = '';
		$cfgName = 'mycloud';
		$db = mysql_connect($this->dbs[$cfgName]['host'], $this->dbs[$cfgName]['user'], $this->dbs[$cfgName]['pwd'], true);
		if (!$db)
		{
			$this->errorMsg = 'Невозможно соединиться с БД ' . $this->dbs[$cfgName]['host'] . '@' . $this->dbs[$cfgName]['user'];
			return false;
		}
		mysql_select_db($this->dbs[$cfgName]['name'], $db);
		mysql_query('SET NAMES ' . $this->dbs[$cfgName]['locale'], $db);

		$sql = '
			SELECT fv.id, fl.server_id, fl.state, fl.folder FROM dm_files_variants fv
				INNER JOIN dm_filelocations AS fl ON (fl.id = fv.id)
				WHERE fv.file_id = ' . $originalId . ' AND fv.preset_id=0 LIMIT 1
		';
		$q = mysql_query($sql, $db);
		$oldFileInfo = mysql_fetch_assoc($q);
		mysql_free_result($q);

		//УДАЛЯЕМ ФАЙЛ
		$sql = 'DELETE FROM dm_filelocations WHERE id = ' . $oldFileInfo['id'];
		$q = mysql_query($sql, $db);
		//УДАЛЯЕМ ВАРИАНТ
		$sql = 'DELETE FROM dm_files_variants WHERE id = ' . $oldFileInfo['id'];
		$q = mysql_query($sql, $db);

		mysql_close($db);

		return true;
	}

	public function getObjectToQueue($originalId, $originalVariantId = 0)
	{
		$condition = 'uf.id = ' . $originalId;
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
			$limit = 'LIMIT 2';
		}
		else
		{
			$limit = '';
			$condition = ' AND ' . $condition;
		}
		$queue = array();
		$cfgName = 'mycloud';
		$this->errorMsg = '';
		$db = mysql_connect($this->dbs[$cfgName]['host'], $this->dbs[$cfgName]['user'], $this->dbs[$cfgName]['pwd'], true);
		if (!$db)
		{
			$this->errorMsg = 'Невозможно соединиться с БД ' . $this->dbs[$cfgName]['host'] . '@' . $this->dbs[$cfgName]['user'];
			return false;
		}
		mysql_select_db($this->dbs[$cfgName]['name'], $db);
		mysql_query('SET NAMES ' . $this->dbs[$cfgName]['locale'], $db);

		//ВЫЧИТЫВАЕМ ИНФО О ФАЙЛЕ И ЕГО ЛОКАЦИИ
		$sql = '
			SELECT uf.id, uf.title, fl.fname, fl.folder, fv.fmd5 FROM dm_userfiles AS uf
				INNER JOIN dm_files_variants as fv ON (fv.file_id = uf.id)
				INNER JOIN dm_filelocations AS fl ON (fl.id = fv.id)
				WHERE fv.preset_id = 0 ' . $condition . ' ORDER BY uf.id
		';
		$q = mysql_query($sql, $db);
		$currentId = 0;
		while ($r = mysql_fetch_assoc($q))
		{
			if (empty($queue[$r['id']]))
			{
				$files = array();
				$md5s = array();
				$ovids = array();
			}

			$files[] = "/" . $r['folder'] . "/" . $r['fname'];
			$md5s[] = $r['fmd5'];

			$tags = array(
				"title"				=> $r['title'],
				"title_original"	=> $r['title'],
//ДОПИСАТЬ ВЫБОРКУ ИЗ ПАРАМЕТРОВ user_objects
				"description"		=> "",
//ДОПИСАТЬ ВЫБОРКУ ИЗ ПАРАМЕТРОВ user_objects
				"year"				=> 0,
			);
			$queue[$r['id']] = array(
				'original_id' => $r['id'],
				'just_online' => 0,
				'files' => $files,
				'md5s' => $md5s,
				'ovids' => $ovids,
				'tags' => $tags,
			);
		}
		mysql_free_result($q);

		if (!empty($queue))
		{
			foreach ($queue as $k => $q)
			{
		//ОПРЕДЕЛЯЕМ СПИСОК ЖАНРОВ
		//ОПРЕДЕЛЯЕМ СПИСОК СТРАН
		//ОПРЕДЕЛЯЕМ ПОСТЕР
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
			_COPY_PATH_,
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
	}
}
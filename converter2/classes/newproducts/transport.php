<?php
/**
 *
 * транспорт витрин, подготовка контента, добавленного администратором, к форматам облака
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
					$cmds[] = 'rsync -r --delete-after --size-only "' . _SRC_PATH_ . $f . '" "' . _COPY_PATH_ . $f . '" 2>&1';
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
		$newDir = _SRC2_PATH_ . $oldInfo['dirname'] . '/' . $subDir;
//echo $newDir . "\n";
		if ($this->createTree(_SRC2_PATH_, $newDir))
			$cmd[] = "rsync -r --delete-after --size-only \"" . $newName . "\" \"" . $newDir . '/' . basename($newName) . '"';
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

		//ВЫБИРАЕМ ИНФОРМАЦИЮ ОБ ИСПОЛНЯЕМОЙ ОЧЕРЕДИ (для получения group_id)
		$sql = 'SELECT * FROM dm_income_queue WHERE original_id = ' . $originalId . ' AND cmd_id = ' . _CMD_SAVEBACK_ . ' AND partner_id = ' . _PARTNER_ID_;
		$q = mysql_query($sql, $db);
		$queueInfo = mysql_fetch_assoc($q);
		$info = unserialize($queueInfo['info']);
		mysql_free_result($q);

		//ВЫБИРАЕМ ОРИГИНАЛЬНЫЙ ВАРИАНТ
		$sql = 'SELECT * FROM dm_product_variants WHERE id = ' . $originalId;
		$q = mysql_query($sql, $db);
		$variantInfo = mysql_fetch_assoc($q);
		mysql_free_result($q);

		if (($variantInfo['childs'] == ',,') && !empty($info['group_id']))
		{
			//НАДО ДОБАВИТЬ ПРЕДКА
			//ИЩЕМ ПРЕДКА С ТАКИМ group_id
			$sql = 'SELECT * FROM dm_product_variants WHERE original_id = ' . $info['group_id'] . ' AND product_id = ' . $variantInfo['product_id'];
			$q = mysql_query($sql, $db);
			$parentExists = mysql_fetch_assoc($q);
			mysql_free_result($q);

			if (!empty($parentExists))
			{
				//ДОБАВЛЯЕМ ПРЕДКУ ЭТОГО ПОТОМКА
				$childs = $this->getChildsIds($parentExists['childs']);
				$childs[$variantInfo['id']] = $variantInfo['id'];
				$parentExists['childs'] = ',' . implode(',', $childs) . ',';
				$sql = 'UPDATE dm_product_variants SET childs = "' . $childs . '" WHERE id = ' . $parentExists['id'];
				mysql_query($sql, $db);
			}
			else
			{
				//СОЗДАЕМ ПРЕДКА
				$parentExists = $variantInfo;
				$parentExists['childs'] = ',' . $variantInfo['id'] . ',';
				$parentExists['original_id'] = $info['group_id'];
				$sql = 'INSERT INTO dm_product_variants (id, product_id, online_only, type_id, active,
					title, description, original_id, childs, sub_id, cloud_ready, cloud_state, cloud_compressor)
				VALUES (NULL, ' . $parentExists['product_id'] . ', ' . $parentExists['online_only'] . ', ' .
				$parentExists['type_id'] . ', 0, "", "", ' . $parentExists['original_id'] . ', "' .
				$parentExists['childs'] . '", ' . $parentExists['sub_id'] . ', 1, 0, 0)';

				mysql_query($sql, $db);
			}

			//ОБНОВЛЯЕМ ПОТОМКА (ВАРИАНТ ГОТОВ)
			$sql = 'UPDATE dm_product_variants SET childs = "", active=0, cloud_ready=1 WHERE id = ' . $variantInfo['id'];
			mysql_query($sql, $db);
		}
		else
		{
			//ВАРИАНТ ГОТОВ
			$sql = 'UPDATE dm_product_variants SET active=0, cloud_ready=1 WHERE id = ' . $variantInfo['id'];
			mysql_query($sql, $db);
		}

		//ПРОВЕРЯЕМ НАЛИЧИЕ В БАЗЕ ЗАПИСИ О ДАННОМ КАЧЕСТВЕ ДЛЯ ЭТОГО ВАРИАНТА
		$sql = 'SELECT * FROM dm_variant_qualities WHERE variant_id = ' . $variantInfo['id'] . ' AND preset_id = ' . $qualityInfo['id'];
		$q = mysql_query($sql, $db);
		$qualityExists = mysql_fetch_assoc($q);
		mysql_free_result($q);

		if (empty($qualityExists))
		{
			//ДОБАВЛЯЕМ СВЯЗЬ С КАЧЕСТВОМ
			$qualityExists = array(
				'variant_id' => $variantInfo['id'],
				'preset_id' => $qualityInfo['id'],
			);
			$sql = 'INSERT INTO dm_variant_qualities (id, variant_id, preset_id)
				VALUES (NULL, ' . $qualityExists['variant_id'] . ', ' . $qualityExists['preset_id'] . ')
			';
			mysql_query($sql, $db);
			$qualityExists['id'] = mysql_insert_id($db);
		}

		//ПРОВЕРЯЕМ НАЛИЧИЕ В БАЗЕ ЗАПИСИ О ФАЙЛЕ
		$sql = 'SELECT * FROM dm_product_files WHERE variant_quality_id = ' . $qualityExists['id'] . ' AND preset_id = ' . $qualityInfo['id'];
		$q = mysql_query($sql, $db);
		$fileExists = mysql_fetch_assoc($q);
		mysql_free_result($q);

		if (empty($fileExists))
		{
			//ДОБАВЛЯЕМ ФАЙЛ
			$fileExists = array(
				'size' => $fInfo['size'],
				'md5' => $fInfo['md5'],
				'fname' => $fInfo['path'] . '/' . $preset . '/' . basename($newName),
				'preset_id' => $qualityInfo['id'],
				'variant_quality_id' => $qualityExists['id'],
			);

			$sql = 'INSERT INTO dm_product_files (id, `size`, md5, fname, preset_id, variant_quality_id)
				VALUES (NULL, ' . $fileExists['size'] . ', ' . $fileExists['md5'] . ', "' . $fileExists['fname'] . '", ' .
				$fileExists['preset_id'] . ', ' . $fileExists['variant_quality_id'] . ')
			';
			mysql_query($sql, $db);
		}
//ВРОДЕ БЫ ГОТОВО К ОТЛАДКЕ НА КОМПРЕССОРЕ

		mysql_close($db);
	}

	public function dropOriginal($originalId)
	{
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
		$queue = array();
		if (empty($condition))
		{
			$limit = 'LIMIT ' . _QUEUE_LIMIT_;
		}
		else
		{
			$limit = '';
			$condition = ' AND ' . $condition;
		}
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

		//ВЫЧИТЫВАЕМ ИНФО О ПРОДУКТЕ И ВАРИАНТАХ НЕГОТОВЫХ ДЛЯ ОБЛАКА
		//СНАЧАЛА ПОЛУЧАЕМ СПИСОК ВСЕХ ВАРИАНТОВ
		$sql = '
			SELECT pv.product_id, pv.online_only, p.title, pv.title as pvtitle, pv.description as pvdescription, pv.id FROM dm_product_variants AS pv
				INNER JOIN dm_products as p ON (pv.product_id = p.id)
				WHERE pv.cloud_ready = 0 ' . $condition . ' ORDER BY pv.id
		';
		$q = mysql_query($sql, $db);
		$variants = array();
		while ($r = mysql_fetch_assoc($q))
		{
			$variants[$r['id']] = $r;
		}
		mysql_free_result($q);

		if (empty($variants))
		{
			mysql_close($db);
			return $queue;
		}

		//ПОЛУЧАЕМ ПАРАМЕТРЫ ВСЕХ ВАРИАНТОВ ДЛЯ ФОРМИРОВАНИЯ ТЭГОВ
		$inSql = ' AND pvl.variant_id IN (' . implode(',', array_keys($variants)) . ')';
		$sql = '
			SELECT pvl.variant_id, pvl.param_id, pvl.value FROM dm_product_param_values
				AS pvl WHERE pvl.value <> ""' . $inSql;

		$q = mysql_query($sql, $db);
		while ($r = mysql_fetch_assoc($q))
		{
			$pName = '';
			switch ($r['param_id'])
			{
				case 4://ОТНОСИТЕЛЬНЫЙ ПУТЬ К ФАЙЛУ В ШАРЕ ВХОДЯЩЕГО КОНТЕНТА
					$pName = 'fname';
				break;
				case 12://
					$pName = 'title_original';
				break;
				case 10://
					$pName = 'poster';
				break;
				case 13://
					$pName = 'year';
				break;
				case 14://
					$pName = 'country';
				break;
				case 15://
					$pName = 'director';
				break;
				case 18://
					$pName = 'genres';
				break;
				case 11://
					$pName = 'usertitle';
				break;
				case 11://
					$pName = 'userdescription';
				break;
			}
			if (!empty($pName))
				$variants[$r['variant_id']][$pName] = $r['value'];
		}
		mysql_free_result($q);

		//СОЗДАЕМ ОЧЕРЕДЬ ДЛЯ КАЖДОГО ФАЙЛА (ТЭГИ БЕРЕМ ИЗ ПАРАМЕТРОВ, ВЫСТАВЛЯЕМ ГРУППИРОВКУ, ЕСЛИ НЕСКОЛЬКО ФАЙЛОВ В ДОБАВЛЕННОМ ПРОДУКТЕ)
		foreach ($variants as $r)
		{
			if (empty($queue[$r['id']]))
			{
				$files = array();
				$md5s = array();
				$ovids = array();
			}

			$files[] = $r['fname'];
			$md5s[] = '';

			if (!empty($r['pvtitle'])) $r['title'] = $r['pvtitle'];
			if (!empty($r['usertitle'])) $r['title'] = $r['usertitle'];

			$r['description'] = '';
			if (!empty($r['pvdescription'])) $r['description'] = $r['pvdescription'];
			if (!empty($r['userdescription'])) $r['description'] = $r['userdescription'];

			if (empty($r['title_original'])) $r['title_original'] = $r['title'];
			if (empty($r['year'])) $r['year'] = 0;
			if (empty($r['poster'])) $r['poster'] = '';
			if (empty($r['country'])) $r['country'] = '';
			if (empty($r['director'])) $r['director'] = '';

			$tags = array(
				"title"				=> $r['title'],
				"title_original"	=> $r['title_original'],
				"description"		=> $r['description'],
				"year"				=> $r['year'],
				"poster"			=> $r['poster'],
				"country"			=> $r['country'],
				"director"			=> $r['director'],
			);
			$queue[$r['id']] = array(
				'original_id' => $r['id'],
				'group_id' => $r['product_id'],
				'just_online' => $r['online_only'],
				'files' => $files,
				'md5s' => $md5s,
				'ovids' => $ovids,
				'tags' => $tags,
			);
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
	}

	public function updateMedia1($originalId, $oldName, $newName, $preset, $fInfo)
	{
	}

    /**
     * преобразовать значение строкового поля childs варианта в массив идентификаторов
     *
     * @param string $childs
     * @return mixed
     */
    public static function getChildsIds($childs)
    {
		$childs = explode(',', $childs);
		$ids = array();
		foreach ($childs as $v)
		{
			$v = intval($v);
			if (!empty($v))
			{
				$ids[$v] = $v;
			}
		}
    	return $ids;
    }
}
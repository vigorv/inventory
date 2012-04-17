<?php
/**
 * класс обеспечивающий связь с файловыми серверами партнера,
 * с серверами БД партнера
 *
 * этот класс обеспечивает взаимодействие с ресурсами сайта fastlink.ws
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
		if (!empty($posters))
		{
			foreach ($posters as $p)
			{
				$cmds[] = 'wget -O ' . _POSTER_PATH_ . $p . ' http://media1.anka.ws' . $p . ' 2>&1';
			}
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
		$cmd = '';
		$oldInfo = pathinfo($oldName);
		$newDir = _SRC_PATH_ . $oldInfo['dirname'] . '/' . $subDir;
//echo $newDir . "\n";
		if ($this->createTree(_SRC_PATH_, $newDir))
			$cmd = "rsync -r --delete-after --size-only " . $newName . " " . $newDir . '/' . basename($newName);
		return $cmd;
	}

	public function saveBack()
	{
	}

	/**
	 * получить массив данных о необработанных фильмах
	 *
	 * формат возвращаемой структуры
	 * 					$info['original_id'];
						$info['files'] = array(
							относительный путь к I-му файлу объекта в примонтированной директории
							относительный путь к II-му файлу объекта в примонтированной директории
							...
						);
						$info['tags'] = array(
							"title"				=> title			- название объекта
							"title_original"	=> title_original	- оригинальное название объекта
							"genres"			=> genres			- жанры через запятую
							"description"		=> description		- описание объекта
							"year"				=> year				- год
							"poster"			=> poster			- относительный путь к файлу постера
						);
	 * @return mixed
	 */
	public function createQueue()
	{
		$queue = array();
		$cfgName = 'videoxq';
		$db = mysql_connect($this->dbs[$cfgName]['host'], $this->dbs[$cfgName]['user'], $this->dbs[$cfgName]['pwd'], true);
		if (!$db)
		{
			return false;
		}
		mysql_select_db($this->dbs[$cfgName]['name'], $db);
		mysql_query('SET NAMES ' . $this->dbs[$cfgName]['locale'], $db);

		$sql = '
			SELECT f.id, f.title, f.title_en, f.dir, f.description, f.year, ff.file_name, ff.id AS ffid FROM films AS f
				INNER JOIN film_variants as fv ON (fv.film_id = f.id)
				INNER JOIN film_files AS ff ON (ff.film_variant_id = fv.id AND ff.cloud_compressor=0)
			WHERE is_license=1 AND f.active > 0 ORDER BY f.id LIMIT 20
		';
//				INNER JOIN film_genres ON (film_genres.film_id = f.id)
//				INNER JOIN genres as g ON (g.id = film_genres.genre_id)
		$q = mysql_query($sql, $db);
		$currentId = 0;
		while ($r = mysql_fetch_assoc($q))
		{
			if (strpos($r['file_name'], '270/') !== false)//ВЕРСИЮ ДЛЯ МОБИЛЬНЫХ ГЕНЕРИМ ПО НОВОЙ
				continue;

			if (empty($queue[$r['id']]))
			{
				$files = array();
			}

			$letter = strtolower(substr($r['dir'], 0, 1));
			if (($letter >= '0') && ($letter <= '9'))
				$letter = '0-999';
			$files[] = "/" . $letter . "/" . $r['dir'] . "/" . $r['file_name'];
			$sql = 'UPDATE film_files SET cloud_compressor = ' . _STATION_ . ' WHERE id = ' . $r['ffid'];
			mysql_query($sql, $db);

			$tags = array(
				"title"				=> $r['title'],
				"title_original"	=> $r['title_en'],
				"description"		=> $r['description'],
				"year"				=> $r['year'],
			);
			$queue[$r['id']] = array(
				'original_id' => $r['id'],
				'files' => $files,
				'tags' => $tags,
			);
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
		//ОПРЕДЕЛЯЕМ ПОСТЕР
				$sql = 'SELECT file_name, type FROM film_pictures WHERE film_id = ' . $q['original_id'];
				$genres = $smallPosters = $bigPosters = $posters = array();
				$query = mysql_query($sql);
				while ($p = mysql_fetch_array($query))
				{
					switch ($p['type'])
					{
						case "poster":
							$dir = _SL_ . 'posters';
							$posters[] = $dir . _SL_ . basename($p['file_name']);
						break;
						case "smallposter":
							$dir = _SL_ . 'smallposters';
							$smallPosters[] = $dir . _SL_ . basename($p['file_name']);
						break;
						case "bigposter":
							$dir = _SL_ . 'bigposters';
							$bigPosters[] = $dir . _SL_ . basename($p['file_name']);
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
}
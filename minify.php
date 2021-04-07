<?php

/* ================================================================================= //
	Класс для уменьшения размера php файла с возможностью сохранния в отдельном файле
	
	или же создание копии файла (backup).
	
	Если $out не задан, будет создан файл backup.
	
	Читаемый файл становится и записуемым, пример :
	
	/var/www/html/file.php будет переименован в /var/www/html/file.php.backup,
	
	а в /var/www/html/file.php записан обработанный код.
// ================================================================================= */

class MinifyPHP {

	private $inFile, $outFile;
	
	/**
	 * Стандартный конструктор
	 *
	 * @param string $in обязательный путь к читаемому файлу
	 * @param string null $out необязательный путь к записуемому файлу
	 * @return void
	 */
	 
	public function __construct($in, $out = null) {

		$this->inFile  = $in;
		$this->outFile = $out;

	}

	/**
	 * Открываем и читаем файл $this->inFile, возвращаем прочитаные байты
	 *
	 * @param void
	 * @return string
	 */

	private function readPHP() {

		if (!($fd = fopen($this->inFile, "r")))
			return error_log("fopen readPHP ".$this->inFile, 0) && false;

		if (!($code = fread($fd, filesize($this->inFile))))
			return error_log("fread readPHP ".$this->inFile, 0) && false;
		
		if (!fclose($fd))
			return error_log("fclose readPHP ".$this->inFile, 0) && false;

		return $code;

	}

	/**
	 * Проверка совпадения token, которые перед и после T_WHITESPACE // \t \r \n и пробел
	 * 
	 * @param string $token для проверки лексемы, стоит ли пропускать пробел
	 * @return bool
	 */

	private function expressionType($token) {
		/* token с https://www.php.net/manual/ru/tokens.php */
		return  $token == "T_COMMENT"                  || // # or // or /* */
			$token == "T_DOC_COMMENT"              || // /** */
			$token == "T_BOOL_CAST"                || // (bool) or (boolean)
			$token == "T_INT_CAST"                 || // (int) or (integer)
			$token == "T_STRING_CAST"              || // (string)
			$token == "T_OBJECT_CAST"              || // (object)
			$token == "T_ARRAY_CAST"               || // (array)
			$token == "T_DOUBLE_CAST"              || // (real), (double) или (float)
			$token == "T_UNSET_CAST"               || // (unset)
			$token == "T_COALESCE_EQUAL"           || // ??=
			$token == "T_COALESCE"                 || // ??
			$token == "T_CONSTANT_ENCAPSED_STRING" || // "foo" или 'bar'
			$token == "T_DOUBLE_ARROW"             || // =>
			$token == "T_BOOLEAN_AND"              || // && or and
			$token == "T_BOOLEAN_OR"               || // || or or
			$token == "T_CONCAT_EQUAL"             || // .=
			$token == "T_IS_EQUAL"                 || // ==
			$token == "T_IS_NOT_EQUAL"             || // != or <>
			$token == "T_IS_SMALLER_OR_EQUAL"      || // <=
			$token == "T_IS_GREATER_OR_EQUAL"      || // >=
			$token == "T_INC"                      || // ++
			$token == "T_DEC"                      || // --
			$token == "T_PLUS_EQUAL"               || // +=
			$token == "T_MINUS_EQUAL"              || // -=
			$token == "T_POW_EQUAL"                || // **=
			$token == "T_MUL_EQUAL"                || // *=
			$token == "T_DIV_EQUAL"                || // /=
			$token == "T_IS_IDENTICAL"             || // ===
			$token == "T_SPACESHIP"                || // <=>
			$token == "T_IS_NOT_IDENTICAL"         || // !==
			$token == "T_AND_EQUAL"                || // &=
			$token == "T_MOD_EQUAL"                || // %=
			$token == "T_XOR_EQUAL"                || // ^=
			$token == "T_OR_EQUAL"                 || // |=
			$token == "T_SL"                       || // <<
			$token == "T_SR"                       || // >>
			$token == "T_SL_EQUAL"                 || // <<=
			$token == "T_SR_EQUAL";                   // >>=
	}

	/**
	 * Улучшенная альтернатива php_strip_whitespace
	 * https://www.php.net/manual/ru/function.php-strip-whitespace.php, 
	 * php_strip_whitespace не убирает лишние пробелы, 
	 * без которых все корректно для zend engine (но хуже читаемость человеком кода).
	 * Пример : else $variable, else if, if (...
	 *
	 * @param string $code не обработанный php код
	 * @return string $buffer обработанный php код
	 */

	private function clearPHP($code) {
		
		$tokens = token_get_all($code);
		$buffer = "";
		$tname  = "";

		foreach($tokens as $k => $token) {    
			
			$tname = @token_name($token[0]);
			
			if((is_array($token) && $tname !== 'T_COMMENT') 
				|| !is_array($token)) {

				if (is_array($token)) {

					if ($tname == "T_WHITESPACE") {

						$prev_token = "";

						if (is_array($tokens[$k - 1])) {

							$prev_token = token_name($tokens[$k - 1][0]);

							if ($prev_token == "T_IF" || $this->expressionType($prev_token))
								continue;

						} else continue;

						if (is_array($tokens[$k + 1])) {

							$next_token = token_name($tokens[$k + 1][0]);

							if (($prev_token == "T_ELSE" && $next_token == "T_VARIABLE") ||
							    ($prev_token == "T_ELSE" && $next_token == "T_IF") ||
							     $this->expressionType($next_token))
							     continue;

						} else continue;
					} 
					
					if ($tname == "T_CONSTANT_ENCAPSED_STRING" ||
						$tname == "T_INLINE_HTML") {	
						$token = $token[1];		
						goto LF_TAB_CLEAR;
					} else $buffer .= $token[1];

				} else {
					LF_TAB_CLEAR:
					if (strpos($token, "\n") !== false ||
						strpos($token, "\t") !== false) {
						 $token   = preg_replace('/\n|\t/s', ' ', $token);
						 $buffer .= preg_replace('/\s{2,}/s', ' ', $token);

					} else $buffer .= $token;
				}
			}
		}

		return $buffer;

	}

	/**
	 * Проверка синтаксиса php https://www.php.net/manual/ru/function.php-check-syntax.php
	 * так как функция php_check_syntax устарела и доступна только (PHP 5 < 5.0.5), 
	 * используем альтернативу, учитывая замечание указанное по ссылке выше
	 *
	 * @param bool $name указывает какой файл проверять: 1 читаемый или 0 записываемый
	 * @return bool
	 */

	private function checkSyntax($name) {
		
		$check_syntax = shell_exec("php -l ".
			escapeshellarg($name ? $this->inFile : $this->outFile));
		
		return substr($check_syntax, 0, 2) == "No" ? true :
			error_log("check php syntax ".$check_syntax, 0) && false;

	}

	/**
	 * Создаем файл или открываем файл и записываем обработанный php код
	 *
	 * @param bool $is_chmod 0 файл открываем, 1 файл создаем и выставляем права
	 * @return bool
	 */

	private function writePHP($is_chmod) {
		
		if (!($fd = fopen($this->outFile, "w+")))
			return error_log("fopen writePHP ".$this->outFile, 0) && false;
		
		if ($is_chmod)
			if (!chmod($this->outFile, 0666))
				return error_log("chmod writePHP ".$this->outFile, 0) && false;

		if (!($code = $this->readPHP())) return false;

		if (!fwrite($fd, $this->clearPHP($code)))
			return error_log("fread writePHP ".$this->outFile, 0) && false;

		if (!fclose($fd))
			return error_log("fclose writePHP ".$this->outFile, 0) && false;

		return $this->checkSyntax(0);

	}

	/**
	 * Изменение имени старого файла (с проверкой на то делался ли уже backup этого файла)
	 * Ещё не готова...
	 *
	 * @param string $nameBackup имя файла backup
	 * @return bool
	 */

	private function renameBackup($nameBackup) {	

		return true; // rename current backup file

	}

	/**
	 * Переименование $this->inFile на $this->inFile.".backup", 
	 * а в $this->inFile записываем обработанный код
	 *
	 * @param void
	 * @return bool
	 */

	private function createBackup() {

		if (!is_writable(dirname($this->inFile))) return false;
		
		$nameBackup = $this->inFile.".backup";

		if (is_file($nameBackup)) 
			if (!$this->renameBackup($nameBackup))
				return false;

		if (!rename($this->inFile, $nameBackup)) 
			return error_log("rename Run ".$this->inFile.", ".$nameBackup, 0) && false;

		$this->outFile = $this->inFile;

		$this->inFile  = $nameBackup;
		
		return true;
		
	}

	/**
	 * Проверяем есть ли файл для записи, 
	 * если нет - делаем backup и запускам обработку php кода, 
	 * а если есть проверяем на запись и запускаем ту же обработку
	 *
	 * @param void
	 * @return bool
	 */
	 
	public function Run() {

		if (!is_string($this->inFile) || 
			!is_readable($this->inFile))
			return error_log("inFile is not string / readable Run ".var_export($this->inFile, 1), 0) && false;

		if (!$this->checkSyntax(1)) return false;

		if ($this->outFile == null) { // записываемый файл не передан, делаем бекап
			
			if (!$this->createBackup()) return false;
			
			return $this->writePHP(1);
			
		} else {
			if (!is_string($this->outFile))
				return error_log("outFile is not string ".var_export($this->outFile, 1), 0) && false;
		}
		
		// иначе проверяем доступ на запись.
		if (!is_writable($this->outFile))
			// если файл существует но не доступен для записи или файла нет но директория не доступа для записи
			if (is_file($this->outFile) 
				|| !is_writable(dirname($this->outFile))) return false;
			
		return $this->writePHP(0);

	}

}

// записываем в указанный (/var/www/html/app/controllers/AjaxController.min) файл
var_dump((new MinifyPHP("/var/www/html/app/controllers/AjaxController.php",
	"/var/www/html/app/controllers/AjaxController.min"))->Run()); 

// записываем в /var/www/html/app/controllers/AjaxController.php 
// создавая /var/www/html/app/controllers/AjaxController.php.backup
// var_dump((new MinifyPHP("/var/www/html/app/controllers/AjaxController.php"))->Run());

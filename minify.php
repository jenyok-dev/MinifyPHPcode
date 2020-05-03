<?php

/* Класс для уменьшения размера php файла с созданием копии файла (backup) */

class MinifyPHP {

	private $inFile, $outFile;
	
	/**
	 * Конструктор в котором принимаем путь к файлу и назначаем его 
	 * внутренней переменной $this->inFile
	 *
	 * @param string $in путь к обрабатываемому файлу
	 * @return void
	 */
	 
	public function __construct($in) {

		$this->inFile = $in;

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

		return $code; //return php_strip_whitespace($this->inFile);	

	}

	/**
	 * Проверка совпадения token, которые перед и после T_WHITESPACE // \t \r \n и пробел
	 * 
	 * @param string $token для проверки лексемы, стоит ли пропускать пробел
	 * @return bool
	 */

	private function expressionType($token) {
		/* типы с https://www.php.net/manual/ru/tokens.php */
		return  $token == "T_COMMENT"                  || // # or // or /* */
				$token == "T_DOC_COMMENT"              || // /** */
				$token == "T_BOOL_CAST"                || // (bool) or (boolean)
				$token == "T_INT_CAST"                 || // (int) or (integer)
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
		$debug  = "";

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
					
					if ($tname == "T_CONSTANT_ENCAPSED_STRING") {	
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

		return $buffer; // 

	}

	/**
	 * Создаем файл с нужными правами и записываем обработанный php код
	 *
	 * @param void
	 * @return bool
	 */

	private function writePHP() {
		
		if (!($fd = fopen($this->outFile, "w+")))
			return error_log("fopen writePHP ".$this->outFile, 0) && false;

		if (!chmod($this->outFile, 0666))
			return error_log("chmod writePHP ".$this->outFile, 0) && false;

		if (!($code = $this->readPHP())) return false;

		if (!(fwrite($fd, $this->clearPHP($code))))
			return error_log("fread writePHP ".$this->outFile, 0) && false;

		if (!fclose($fd))
			return error_log("fclose writePHP ".$this->outFile, 0) && false;

		return $this->checkSyntax(0);

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
	 * Проверяем доступно ли читать и писать, 
	 * если всё доступно делаем backup и запускам процесс обработки php кода
	 *
	 * @param void
	 * @return bool
	 */
	 
	public function Run() {

		if (!is_readable($this->inFile))
			return error_log("readable Run ".$this->inFile, 0) && false;

		if (!$this->checkSyntax(1)) return false;

		// если владелец php не root
		if (posix_getuid()) {

			$stat = stat(dirname($this->inFile));

			// если владелец или группа php не совпадают с владельцем или группой директории с $this->inFile
			if ($stat["uid"] != posix_getuid() && 
				$stat["gid"] != posix_getgid()) {
				if (!($stat['mode'] & 0x0002)) // проверяем доступно ли гостям писать в директории
					return error_log("permission write denied ".
						substr(sprintf('%o', $stat['mode']), -4), 0) && false;
			}

		}

		$nameBackup = $this->inFile.".backup";

		if (is_file($nameBackup)) 
			if (!$this->renameBackup($nameBackup))
				return false;

		if (!rename($this->inFile, $nameBackup)) 
			return error_log("rename Run ".$this->inFile.", ".$nameBackup, 0) && false;

		$this->outFile = $this->inFile;

		$this->inFile  = $nameBackup;

		return $this->writePHP();

	}

}

var_dump((new MinifyPHP("/var/www/html/app/controllers/AjaxController.php"))->Run());

<?php

class TessCardReader{
	
	/**
	 * The path to the input image file
	 * @var string 
	 */
	private $input_file;
	
	/**
	 * Last error message
	 * @var string 
	 */
	private $last_error;
	
	/**
	 * Has there been any errors
	 * @var boolean
	 */
	private $error = false;
	
	/**
	 * The raw text to be parsed
	 * @var string 
	 */
	private $raw_text;
	
	/**
	 * Possible websites
	 * @var array
	 */
	private $possible_websites;
	
	/**
	 * Auto-corrected $raw_text
	 * @var string
	 */
	private $corrected_output;
	
	/**
	 * Array of possible names
	 * @var array
	 */
	private $possible_names;
	
	/**
	 * Array of possible emails
	 * @var array
	 */
	private $possible_emails;
	
	/**
	 * Array of possible company names
	 * @var array
	 */
	private $possible_company_names;
	
	/**
	 * Array of possible addresses
	 * @var array
	 */
	private $possible_addresses;
	
	/**
	 * possible phone numbers
	 * @var array
	 */
	private $possible_phones;
	
	/**
	 * Output broken into lines
	 * @var array
	 */
	private $output_lines;
	
	/**
	 * Output broken into groups
	 * @var array 
	 */
	private $output_groups;
	
	/**
	 * Path to the required assets
	 * @var string
	 */
	private $assets_path;
	
	/**
	 * The path to the tesseract executable
	 * @var string 
	 */
	private $tesseract_path;
	
	############################################################################
	## CONSTRUCTORS ############################################################
	############################################################################
	
	/**
	 * Parse data from an image
	 * @param string $input_file - path to image to parse
	 * @param string $tesseract_path - path to tesseract program
	 * @return \TessCardReader
	 */
	public static function fromImage($input_file, $tesseract_path='tesseract'){
		return new TessCardReader($input_file, 'FILE', $tesseract_path);
	}
	
	/**
	 * Parse data from a block of text
	 * @param string $input - raw text to parse
	 * @return \TessCardReader
	 */
	public static function fromText($input){
		return new TessCardReader($input, 'TEXT');
	}
	
	/**
	 * Class constructor
	 * @param string $input_file - Path to the input image file
	 */
	private function __construct($input, $input_type, $tesseract_path='tesseract'){
		if($input_type === 'FILE'){
			$this->input_file = $input;
			$this->assets_path = realpath(dirname(__FILE__))."/assets";
			$this->tesseract_path = $tesseract_path;
			$this->parseImage();
		}else{
			$this->raw_text = $input;
		}
	}
	
	############################################################################
	## PUBLIC FUNCTIONS ########################################################
	############################################################################
	
	/**
	 * Get the last error, if there is one
	 * @return string
	 */
	public function getError(){
		return $tcr->last_error;
	}
	
	/**
	 * Has an error occurred?
	 * @return bool
	 */
	public function hasError(){
		return $this->error;
	}
	
	/**
	 * Get raw text being parsed
	 * @return string
	 */
	public function rawText(){
		return $this->raw_text;
	}
	
	/**
	 * Extract individual lines from the output.
	 */
	public function getLines(){
		if(!empty($this->output_lines)) return $this->output_lines;
		$lines = explode("\n", $this->raw_text);
		$l = array();
		foreach($lines as $line){
			$line = trim($line);
			if(!empty($line)) $l[] = $line;
		}
		$this->output_lines = $l;
		return $this->output_lines;
	}
	
	/**
	 * Extract groupings from the output.
	 * @return type
	 */
	public function getGroups(){
		if(!empty($this->output_groups)) return $this->output_groups;
		$groups = explode("\n\n", $this->raw_text);
		$l = array();
		foreach($groups as $group){
			$group = trim($group);
			if(!empty($group)) $l[] = $group;
		}
		$this->output_groups = $l;
		return $this->output_groups;
	}
	
	/**
	 * Attempt to auto-correct Tesseract output
	 */
	public function autoCorrect(){
		if(!empty($this->corrected_output)) return $this->corrected_output;
			
		$handle = fopen($this->assets_path."/data/words-eng.txt", "r");
		if(false === $handle){
			$this->last_error = $res->stderr;
			$this->error = true;
			return '';
		}
		$clines = array();
		$lines = explode("\n", $this->raw_text);	
		foreach($lines as $line){
			$cwords = array();
			$words = explode(" ", $line);
			foreach($words as $word){
				$word = trim($word);
				if(empty($word)){
					$cwords[] = $word;
					continue;
				}
				$matches = self::levenshteinMatches($handle, $word, 1, 60);				
				if(count($matches) > 1){
					$cwords[] = $matches[0]->word;
				}else{
					$cwords[] = $word;
				}
			}
			$clines[] = implode(" ", $cwords);
		}
		fclose($handle);
		$this->corrected_output = implode("\n", $clines);
		return $this->corrected_output;
	}
	
	/**
	 * Use the most common first and last names to try and assert name from a multi-line string
	 */
	public function extractNames(){
		if(!empty($this->possible_names)) return $this->possible_names;
		
		$possibles = array();
		$namelists = array("names-first.txt", "names-last.txt");
		foreach($namelists as $list){
			$handle = fopen($this->assets_path."/data/".$list, "r");
			while (!feof($handle)) {
				$name = strtolower(trim(fgets($handle)));
				
				$re = '/[a-zA-Z.\']{1,15}\s[a-zA-Z.\']{0,15}\s?[a-zA-Z.\']{1,15}/';
				$all_matches = array();
				$all_lines = $this->getLines();
				foreach($all_lines as $line){
					while(strlen($line) > 0){
						preg_match_all($re, $line, $matches);
						$all_matches = array_merge($all_matches, $matches[0]);
						$line = explode(" ", $line);
						array_shift($line);
						$line = implode(" ", $line);
						$line = trim($line);
					}
				}
				$all_matches = array_unique($all_matches);
				
				foreach($all_matches as $line){
					$lc_line = trim(strtolower($line));
					$matches = array();
					if(empty($lc_line) || empty($name)) continue;
					preg_match('/\b'.str_replace('-','\-',$name).'\b/', $lc_line, $matches);
					if(!empty($matches)){
						
						// skip email addresses
						if(strpos($line, '@') !== false) continue;
						
						$found = false;
						foreach($possibles as $k=>$pn){
							if($pn['line'] === $line){
								$found = true;
								$possibles[$k]['match'][] = $name;
								$possibles[$k]['score']++;
								break;
							}
						}

						if(!$found){
							$possibles[] = array(
								'line' => $line,
								'match' => array($name),
								'score' => 1
							);
						}
						
					}
				}
			}
			fclose($handle);
		}
		
		usort($possibles, array("TessCardReader", "scoreCmp"));
		$this->possible_names = array();
		foreach($possibles as $p){
			
			$name = trim($p['line']);
			$name = explode(' ', $p['line']);
			$last = trim(array_pop($name));
			$first = trim(implode(' ', $name));
			
			$this->possible_names[] = array(
				'full' => $p['line'],
				'first' => $first,
				'last' => $last
			);
		}
		return $this->possible_names;
	}
	
	/**
	 * Extract and format phone numbers from a string
	 */
	public function extractPhoneNumbers(){
		if(!empty($this->possible_phones)) return $this->possible_phones;
		
		$common_mistakes = array(
			"o" => "0",
			"i" => "1",
			"s" => "5",
			"|" => "1"
		);
		$phones = array();
		$all_lines = $this->getLines();
		foreach($all_lines as $line){
			$line = strtolower(preg_replace('/\s/', '', $line));

			foreach($common_mistakes as $s=>$r){
				$line = str_replace($s, $r, $line);
			}

			$p = preg_match_all('/\b\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})\b/', $line, $matches);
			if(!empty($p)){
				foreach($matches[0] as $m){
					$n = preg_replace("/[^0-9]/", "", $m);   
					$phones[] = "(".substr($n, -10, -7).") ".substr($n, -7, -4)."-".substr($n, -4); 
				}
			}
		}
		$this->possible_phones = $phones;
		return $this->possible_phones;
	}
	
	/**
	 * Given a multiline string, this function returns an array of lines
	 * that probably contain a company name based.
	 * https://www.harborcompliance.com/information/company-suffixes
	 * @param bool $fuzzy - consider common OCR mistakes
	 * @return array
	 */
	public function extractCompanyNames($fuzzy=true){
		if(!empty($this->possible_company_names)) return $this->possible_company_names;
		
		$possibles = array();
		$common_mistakes = array(
			"0" => array("o"),
			"o" => array("0"),
			"1" => array("i", "l"),
			"i" => array("1", "l"),
			"l" => array("i", "1"),
			"5" => array("s"),
			"s" => array("5")
		);
		$handle = fopen($this->assets_path."/data/company-indicators.txt", "r");
		while (!feof($handle)) {
			$k = strtolower(trim(fgets($handle)));
			$k = str_replace('.', '\.', $k);
			
			foreach($common_mistakes as $kk=>$v){
				$k = str_replace($kk, "($kk|".implode('|', $v).")", $k);
			}
			$regex = '/.*?\b'.$k.'\b.*?/';
			$r = preg_match($regex, strtolower($this->raw_text), $matches, PREG_OFFSET_CAPTURE);
			if(!empty($r)){
				$add = true;
				if($fuzzy){
					foreach($possibles as $p){
						if(strpos($p[0], $matches[0][0]) !== false) $add = false;
					}
				}
				if($add) $possibles[] = $matches[0];
			} 
		}
		$p = array();
		foreach($possibles as $po){
			$p[] = trim(substr($this->raw_text, $po[1], strlen($po[0])));
		}
		$this->possible_company_names = $p;
		return $this->possible_company_names;
	}
	
	/**
	 * extract websites from text
	 */
	public function extractWebsites(){
		if(!empty($this->possible_websites)) return $this->possible_websites;
		$this->possible_websites = array();
		$str = " ".strtolower($this->raw_text);
		$regex = '/\s(http:\/\/www\.|https:\/\/www\.|http:\/\/|https:\/\/)?[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}(:[0-9]{1,5})?(\/.*)?\b/';
		preg_match_all($regex, $str, $match);
		if(!empty($match) && !empty($match[0])){
			foreach($match[0] as $m){
				$this->possible_websites[] = trim($m);
			}
		}
		return $this->possible_websites;
	}
	
	/**
	 * extract emails from text
	 */
	public function extractEmails(){
		if(!empty($this->possible_emails)) return $this->possible_emails;
		$this->possible_emails = array();
		$str = " ".strtolower($this->raw_text);
		$regex = '/\b(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){255,})(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){65,}@)(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22))(?:\.(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-[a-z0-9]+)*\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-[a-z0-9]+)*)|(?:\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\]))\b/iD';
		preg_match_all($regex, $str, $match);
		if(!empty($match) && !empty($match[0])){
			foreach($match[0] as $m){
				$this->possible_emails[] = trim($m);
			}
		}
		return $this->possible_emails;
	}
	
	/**
	 * Extract the address from the output
	 */
	public function extractStreetAddress(){
		if(!empty($this->possible_addresses)) return $this->possible_addresses;
		
		$possibles = array();
		$re = '/\b\d{1,9}\s[0-9a-zA-Z#\. ]+\b/';
		$all_matches = array();
		$output_groups = $this->getGroups();
		foreach($output_groups as $gn=> $group){
			$lines = explode("\n", $group);
			foreach($lines as $ln=> $line){
				while(strlen($line) > 0){
					preg_match_all($re, $line, $matches, PREG_OFFSET_CAPTURE);
					foreach($matches as $k=> $v){
						if(empty($v)) continue;
						foreach($matches[$k] as $kk=> $vv){
							if(empty($matches[$k][$kk])) continue;
							$all_matches[] = array(
								'grp_no'=>$gn,
								'ln_no'=>$ln,
								'ln_pos'=>$matches[$k][$kk][1],
								'full'=>'',
								'street'=>$matches[$k][$kk][0],
								'unit'=>'',
								'city'=>'',
								'state'=>'',
								'zip'=>'',
								'score'=>1
							);
						}
					}
					$line = explode(" ", $line);
					array_shift($line);
					$line = implode(" ", $line);
					$line = trim($line);
				}
			}
		}
		
		// Remove dupes - not sure if this is neccesary
		foreach($all_matches as $k=>$v) $all_matches[$k] = json_encode($v);
		$all_matches = array_unique($all_matches);
		foreach($all_matches as $k=>$v) $all_matches[$k] = json_decode($v, true);
		
		// Extract address parts
		foreach($all_matches as $k=>$v){
			$all_matches[$k]['unit'] = $this->extractUnit($all_matches[$k]);
			if($all_matches[$k]['unit'] !== '') $all_matches[$k]['score']++;
			
			$all_matches[$k]['state'] = $this->extractState($all_matches[$k]);
			if($all_matches[$k]['state'] !== '') $all_matches[$k]['score']++;
			
			$all_matches[$k]['city'] = $this->extractCity($all_matches[$k]);
			if($all_matches[$k]['city'] !== '') $all_matches[$k]['score']++;
			
			$all_matches[$k]['zip'] = $this->extractZip($all_matches[$k]);
			if($all_matches[$k]['zip'] !== '') $all_matches[$k]['score']++;
			
			$all_matches[$k]['full'] = $this->extractFullAddr($all_matches[$k]);
		}
		
		usort($all_matches, array("TessCardReader", "scoreCmp"));
		$this->possible_addresses = array();
		
		foreach($all_matches as $addr){			
			$this->possible_addresses[] = array(
				'full' => $addr['full'],
				'street' => $addr['street'],
				'unit' => $addr['unit'],
				'city' => $addr['city'],
				'state' => $addr['state'],
				'zip' => $addr['zip'],
			);
		}
		return $this->possible_addresses;
	}
	
	############################################################################
	## PRIVATE FUNCTIONS #######################################################
	############################################################################
	
	/**
	 * Get and parse Tesseract output
	 */
	private function parseImage(){
		$res = self::runCmd("{$this->tesseract_path} \"{$this->input_file}\" stdout");
		if($res->exit_status !== 0){
			$this->last_error = $res->stderr;
			$this->error = true;
		}else{
			$this->raw_text = $res->stdout;
		}
	}
	
	/**
	 * Extract the full address
	 * @param type $addr
	 * @return type
	 */
	private function extractFullAddr($addr){
		$output_groups = $this->getGroups();
		$block = $output_groups[$addr['grp_no']];	
		$start_pos = strpos($block, $addr['street']);
		$end_pos = $start_pos + strlen($addr['street']);
		
		if($addr['unit'] !== ''){
			$end_pos = strpos($block, $addr['unit'], $end_pos) + strlen($addr['unit']);
		}
		
		if($addr['city'] !== ''){
			$end_pos = strpos($block, $addr['city'], $end_pos) + strlen($addr['city']);
		}
		
		if($addr['state'] !== ''){
			$end_pos = strpos($block, $addr['state'], $end_pos) + strlen($addr['state']);
		}
		
		if($addr['zip'] !== ''){
			$end_pos = strpos($block, $addr['zip'], $end_pos) + strlen($addr['zip']);
		}
		
		$strlen = $end_pos - $start_pos;
		
		return preg_replace('/\s+/', ' ', substr($block, $start_pos, $end_pos));
	}
	
	/**
	 * Attempt to extract a zip code from the address
	 * @param string $addr
	 * @return string
	 */
	private function extractZip($addr){
		$output_groups = $this->getGroups();
		$block = $output_groups[$addr['grp_no']];	
		$start_pos = strpos($block, $addr['street']) + strlen($addr['street']);
		$block = substr($block, $start_pos);
		
		if($addr['unit'] !== ''){
			$start_pos = strpos($block, $addr['unit']) + strlen($addr['unit']);
			$block = substr($block, $start_pos);
		}
		
		if($addr['city'] !== ''){
			$start_pos = strpos($block, $addr['city']) + strlen($addr['city']);
			$block = substr($block, $start_pos);
		}
		
		if($addr['state'] !== ''){
			$start_pos = strpos($block, $addr['state']) + strlen($addr['state']);
			$block = substr($block, $start_pos);
		}
		
		$regex = '/\b[0-9]{5}(-[0-9]{4})?\b/';
		preg_match($regex, $block, $matches, PREG_OFFSET_CAPTURE);
		if(!empty($matches) && !empty($matches[0])){
			return trim(substr($block, $matches[0][1], strlen($matches[0][0])));
		}
		return '';
	}
	
	/**
	 * Extract city from address if possible
	 * @param string $addr
	 * @return string
	 */
	private function extractCity($addr){
		if($addr['state'] === '') return '';
		$output_groups = $this->getGroups();
		$block = $output_groups[$addr['grp_no']];	
		$start_pos = strpos($block, $addr['street']) + strlen($addr['street']);
		$block = substr($block, $start_pos);
		
		if($addr['unit'] !== ''){
			$start_pos = strpos($block, $addr['unit']) + strlen($addr['unit']);
			$block = substr($block, $start_pos);
		}
		
		$end_pos = strpos($block, $addr['state']);
		$block = substr($block, 0, $end_pos);
		
		return trim($block, " \t\n\r\0\x0B,");
	}
	
	/**
	 * Extract the state from the address if exists
	 * @param array $addr
	 * @return string
	 */
	private function extractState($addr){
		$output_groups = $this->getGroups();
		$block = $output_groups[$addr['grp_no']];	
		$start_pos = strpos($block, $addr['street']) + strlen($addr['street']);
		$block = substr($block, $start_pos);
		
		if($addr['unit'] !== ''){
			$start_pos = strpos($block, $addr['unit']) + strlen($addr['unit']);
			$block = substr($block, $start_pos);
		}
		
		$state_indicators = explode("\n", file_get_contents($this->assets_path."/data/addr-state-terr.txt"));
		$state_grp = array();
		foreach($state_indicators as $sind){
			$sind = trim(strtolower($sind));
			if(empty($sind)) continue;
			$state_grp[] = $sind;
		}
		$state_grp = "(".implode("|", $state_grp).")";
		
		$regex = '/\b'.$state_grp.'\.?\b/';
		preg_match($regex, strtolower($block), $matches, PREG_OFFSET_CAPTURE);
		if(!empty($matches) && !empty($matches[0])){
			return trim(substr($block, $matches[0][1], strlen($matches[0][0])));
		}
		return '';
	}
	
	/**
	 * Extract the unit from the address if exists
	 * @param array $addr
	 * @return string
	 */
	private function extractUnit($addr){
		$output_groups = $this->getGroups();
		$block = $output_groups[$addr['grp_no']];
		$start_pos = strpos($block, $addr['street']) + strlen($addr['street']);
		$block = substr($block, $start_pos);
		
		$unit_indicators = explode("\n", file_get_contents($this->assets_path."/data/addr-unit-ind.txt"));
		$unit_grp = array();
		foreach($unit_indicators as $uind){
			$uind = trim(strtolower($uind));
			if(empty($uind)) continue;
			$unit_grp[] = $uind;
		}
		$unit_grp = "(".implode("|", $unit_grp).")";

		$regex = '/\b'.$unit_grp.'\b\.?\s?#?[a-zA-Z0-9]*/';
		preg_match($regex, strtolower($block), $matches, PREG_OFFSET_CAPTURE);
		if(!empty($matches) && !empty($matches[0])){
			return trim(substr($block, $matches[0][1], strlen($matches[0][0])));
		}
		return '';
	}
	
	############################################################################
	## STATIC METHODS ##########################################################
	############################################################################
	
	/**
	 * Find the closest matches to a given word.
	 * @param resource $wordlist_handle - a file handle to a wordlist where each word is 
	 *		on it's own line and in uppercase.
	 * @param string $word - the word to match.
	 * @param number $max_char_distance - maximum number of chars that may be added 
	 *		or removed from a match.
	 * @param number $min_pct_match - minimum match percentage
	 * @return array of objects containing the matched word, levenshtein distance,
	 *		and match percentage. 
	 */
	private static function levenshteinMatches($wordlist_handle, $word, $max_char_distance=2, $min_pct_match=65){
		$possibles = array();
		$word = strtolower(trim($word));
		$word_len = strlen($word);
		rewind($wordlist_handle);
		$max_val = $max_char_distance + $word_len;
		while (!feof($wordlist_handle)) {
			$line = strtolower(trim(fgets($wordlist_handle)));
			$line_len = strlen($line);		
			$too_short = $line_len < $word_len-$max_char_distance;
			$too_long = $line_len > $word_len+$max_char_distance;
			if($too_short || $too_long) continue;			
			$dist = levenshtein($word, $line);
			$pct = 100 - ((100 * $dist) / $max_val);
			if($pct > $min_pct_match) $possibles[] = (object) array(
				'distance' => $dist,
				'word' => $line,
				'percentage' => $pct
			);
		}
		usort($possibles, array("TessCardReader", "levenshteinMatchesCmp"));
		return $possibles;
	}
	
	/**
	 * Levenshtein comparison function
	 * @param string $a
	 * @param string $b
	 * @return int
	 */
	private static function levenshteinMatchesCmp($a, $b){
		if ($a->distance == $b->distance) return 0;
		return ($a->distance < $b->distance) ? -1 : 1;
	}
	
	/**
	 * name comparison function
	 * @param type $a
	 * @param type $b
	 * @return int
	 */
	private static function scoreCmp($a, $b){
		if ($a['score'] == $b['score']) return 0;
		return ($a['score'] > $b['score']) ? -1 : 1;
	}
	
	/**
	 * Adds data to training data
	 * @param string $type - company-indicators|names-first|names-last|words-eng
	 * @param string $data - data to add
	 */
	public static function trainData($type, $data){
		$scripts_path = realpath(dirname(__FILE__))."/assets/scripts";
		$cmd = "./add_data.php -f $type.txt -d ".escapeshellarg($data)." && ./clean_data.php -f $type.txt";
		$res = self::runCmd($cmd, null, $scripts_path);
		return (object) array(
			'error' => $res->exit_status !== 0,
			'message' => trim($res->exit_status === 0 ? $res->stdout : $res->stderr),
			'elapsed' => $res->elapsed
		);
	}
	
	/**
	 * Run a command with optional stdin
	 * @param string $cmd - the command to run
	 * @param string|string[] $stdin - the stdin to feed to the command
	 * @param string $cwd - the working directory in which to run the command
	 * @return object containing exit_status, stdout, stderr and elapsed
	 */
	private static function runCmd($cmd, $stdin=null, $cwd=null){
		if(empty($cwd)) $cwd = getcwd();
		if(empty($stdin)) $stdin = array();
		if(!is_array($stdin)) $stdin = array($stdin);

		$started = microtime(true);

		$descriptorspec = array(
			0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
			1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
			2 => array("pipe", "w")   // stderr is a pipe that the child will write to
		);
		$process = proc_open($cmd, $descriptorspec, $pipes, $cwd);

		$stderr = '';
		$stdout = '';
		$status = 0;

		if (is_resource($process)) {
			foreach($stdin as $input){
				fwrite($pipes[0], $input);
			}
			fclose($pipes[0]);

			$stdout = stream_get_contents($pipes[1]);
			fclose($pipes[1]);

			$stderr = stream_get_contents($pipes[2]);
			fclose($pipes[2]);

			$status = proc_close($process);
		}else{
			$stderr = 'Unable to run command.';
			$status = 1;
		}

		$elapsed = microtime(true) - $started;

		return (object) array(
			'exit_status' => $status,
			'stderr' => $stderr,
			'stdout' => $stdout,
			'elapsed' => $elapsed
		);
	}
	
}

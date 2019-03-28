# TessCardReader

A smart PHP Library for parsing and extracting contact data from images and text.

### Installation/Setup

 1. Install [Tesseract OCR](https://github.com/tesseract-ocr/tesseract) and ensure that the program is in the `$PATH` of the user that PHP runs as. Ensure that PHP is able to run `tesseract`.
 2. `git clone` or download the repo and run the example `index.php` file.

### For best results...

When parsing images, use larger images and crop the background out if at all possible.

### Methods

#### Constructors

	/**
	 * Parse data from an image
	 * @param string $input_file - path to image to parse
	 * @param string $tesseract_path - path to tesseract program (optional)
	 * @return \TessCardReader
	 */
	public static function fromImage($input_file, $tesseract_path='tesseract')

	/**
	 * Parse data from a block of text
	 * @param string $input - raw text to parse
	 * @return \TessCardReader
	 */
	public static function fromText($input)

#### Getters

	/**
	 * Get the last error, if there is one
	 * @return string
	 */
	public function getError()
	
	/**
	 * Has an error occurred?
	 * @return bool
	 */
	public function hasError()
	
	/**
	 * Get raw text being parsed
	 * @return string
	 */
	public function rawText()
	
	/**
	 * Extract individual lines from the output.
	 */
	public function getLines()
	
	/**
	 * Extract groupings from the output.
	 * @return type
	 */
	public function getGroups()
	
	/**
	 * Attempt to auto-correct Tesseract output
	 */
	public function autoCorrect()
	
	/**
	 * Use the most common first and last names to try and assert name from a multi-line string
	 */
	public function extractNames()
	
	/**
	 * Extract and format phone numbers from a string
	 */
	public function extractPhoneNumbers()
	
	/**
	 * Given a multiline string, this function returns an array of lines
	 * that probably contain a company name based.
	 * https://www.harborcompliance.com/information/company-suffixes
	 * @param bool $fuzzy - consider common OCR mistakes
	 * @return array
	 */
	public function extractCompanyNames($fuzzy=true)
	
	/**
	 * extract websites from text
	 */
	public function extractWebsites()
	
	/**
	 * extract emails from text
	 */
	public function extractEmails()
	
	/**
	 * Extract the address from the output
	 */
	public function extractStreetAddress()

### License

MIT

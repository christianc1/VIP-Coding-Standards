<?php
/**
 * WordPressVIPMinimum Coding Standard.
 *
 * @package VIPCS\WordPressVIPMinimum
 * @link https://github.com/Automattic/VIP-Coding-Standards
 */

namespace WordPressVIPMinimum\Sniffs\Security;

use PHP_CodeSniffer\Util\Tokens;
use WordPressVIPMinimum\Sniffs\Sniff;

/**
 * Looks for instances of unescaped output for Underscore.js templating engine.
 *
 * @package VIPCS\WordPressVIPMinimum
 */
class UnderscorejsSniff extends Sniff {

	/**
	 * Regex to match unescaped output notations containing variable interpolation
	 * and retrieve a code snippet.
	 *
	 * @var string
	 */
	const UNESCAPED_INTERPOLATE_REGEX = '`<%=\s*(?:.+?%>|$)`';

	/**
	 * A list of tokenizers this sniff supports.
	 *
	 * @var string[]
	 */
	public $supportedTokenizers = [ 'JS', 'PHP' ];

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		$targets   = Tokens::$textStringTokens;
		$targets[] = T_PROPERTY;
		$targets[] = T_STRING;

		return $targets;
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param int $stackPtr The position of the current token in the stack passed in $tokens.
	 *
	 * @return void
	 */
	public function process_token( $stackPtr ) {
		/*
		 * Check for delimiter change in JS files.
		 */
		if ( $this->tokens[ $stackPtr ]['code'] === T_STRING
			|| $this->tokens[ $stackPtr ]['code'] === T_PROPERTY
		) {
			if ( $this->phpcsFile->tokenizerType !== 'JS' ) {
				// These tokens are only relevant for JS files.
				return;
			}

			if ( $this->tokens[ $stackPtr ]['content'] !== 'interpolate' ) {
				return;
			}

			// Check the context to prevent false positives.
			if ( $this->tokens[ $stackPtr ]['code'] === T_STRING ) {
				$prev = $this->phpcsFile->findPrevious( Tokens::$emptyTokens, ( $stackPtr - 1 ), null, true );
				if ( $prev === false || $this->tokens[ $prev ]['code'] !== T_OBJECT_OPERATOR ) {
					return;
				}

				$prevPrev = $this->phpcsFile->findPrevious( Tokens::$emptyTokens, ( $stackPtr - 1 ), null, true );
				$next     = $this->phpcsFile->findNext( Tokens::$emptyTokens, ( $stackPtr + 1 ), null, true );
				if ( ( $prevPrev === false
					|| $this->tokens[ $prevPrev ]['code'] !== T_STRING
					|| $this->tokens[ $prevPrev ]['content'] !== 'templateSettings' )
					&& ( $next === false
					|| $this->tokens[ $next ]['code'] !== T_EQUAL )
				) {
					return;
				}
			}

			// Underscore.js delimiter change.
			$message = 'Found Underscore.js delimiter change notation.';
			$this->phpcsFile->addWarning( $message, $stackPtr, 'InterpolateFound' );

			return;
		}

		$content     = $this->strip_quotes( $this->tokens[ $stackPtr ]['content'] );
		$match_count = preg_match_all( self::UNESCAPED_INTERPOLATE_REGEX, $content, $matches );
		if ( $match_count > 0 ) {
			foreach ( $matches[0] as $match ) {
				// Underscore.js unescaped output.
				$message = 'Found Underscore.js unescaped output notation: "%s".';
				$data    = [ $match ];
				$this->phpcsFile->addWarning( $message, $stackPtr, 'OutputNotation', $data );
			}
		}

		if ( $this->phpcsFile->tokenizerType !== 'JS'
			&& strpos( $content, 'interpolate' ) !== false
		) {
			// Underscore.js delimiter change.
			$message = 'Found Underscore.js delimiter change notation.';
			$this->phpcsFile->addWarning( $message, $stackPtr, 'InterpolateFound' );
		}
	}

}

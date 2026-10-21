<?php
/**
 * Turns one source (old site) post into the data a Lesson needs.
 *
 * Pure PHP — no WordPress calls — so it can be unit-tested outside WP.
 *
 * @package potw-lesson-importer
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'POTW_LI_TESTING' ) ) {
	exit;
}

class POTW_LI_Parser {

	/**
	 * Canonical Bible book names. Order matters: longer / numbered names first
	 * so "1 John" wins over "John" and "Song of Solomon" over "Song".
	 */
	const BOOKS = array(
		'Song of Solomon' => 'Song of Solomon',
		'Song of Songs'   => 'Song of Solomon',
		'1 Samuel'        => '1 Samuel',
		'2 Samuel'        => '2 Samuel',
		'1 Kings'         => '1 Kings',
		'2 Kings'         => '2 Kings',
		'1 Chronicles'    => '1 Chronicles',
		'2 Chronicles'    => '2 Chronicles',
		'1 Corinthians'   => '1 Corinthians',
		'2 Corinthians'   => '2 Corinthians',
		'1 Thessalonians' => '1 Thessalonians',
		'2 Thessalonians' => '2 Thessalonians',
		'1 Timothy'       => '1 Timothy',
		'2 Timothy'       => '2 Timothy',
		'1 Peter'         => '1 Peter',
		'2 Peter'         => '2 Peter',
		'1 John'          => '1 John',
		'2 John'          => '2 John',
		'3 John'          => '3 John',
		'Genesis'         => 'Genesis',
		'Exodus'          => 'Exodus',
		'Leviticus'       => 'Leviticus',
		'Numbers'         => 'Numbers',
		'Deuteronomy'     => 'Deuteronomy',
		'Joshua'          => 'Joshua',
		'Judges'          => 'Judges',
		'Ruth'            => 'Ruth',
		'Ezra'            => 'Ezra',
		'Nehemiah'        => 'Nehemiah',
		'Esther'          => 'Esther',
		'Job'             => 'Job',
		'Psalms'          => 'Psalms',
		'Psalm'           => 'Psalms',
		'Proverbs'        => 'Proverbs',
		'Ecclesiastes'    => 'Ecclesiastes',
		'Isaiah'          => 'Isaiah',
		'Jeremiah'        => 'Jeremiah',
		'Lamentations'    => 'Lamentations',
		'Ezekiel'         => 'Ezekiel',
		'Daniel'          => 'Daniel',
		'Hosea'           => 'Hosea',
		'Joel'            => 'Joel',
		'Amos'            => 'Amos',
		'Obadiah'         => 'Obadiah',
		'Jonah'           => 'Jonah',
		'Micah'           => 'Micah',
		'Nahum'           => 'Nahum',
		'Habakkuk'        => 'Habakkuk',
		'Zephaniah'       => 'Zephaniah',
		'Haggai'          => 'Haggai',
		'Zechariah'       => 'Zechariah',
		'Malachi'         => 'Malachi',
		'Matthew'         => 'Matthew',
		'Mark'            => 'Mark',
		'Luke'            => 'Luke',
		'John'            => 'John',
		'Acts'            => 'Acts',
		'Romans'          => 'Romans',
		'Galatians'       => 'Galatians',
		'Ephesians'       => 'Ephesians',
		'Philippians'     => 'Philippians',
		'Colossians'      => 'Colossians',
		'Titus'           => 'Titus',
		'Philemon'        => 'Philemon',
		'Hebrews'         => 'Hebrews',
		'James'           => 'James',
		'Jude'            => 'Jude',
		'Revelation'      => 'Revelation',
	);

	/** File extensions treated as lesson documents. */
	const DOC_EXT = array( 'pdf', 'doc', 'docx' );

	/**
	 * Parse a source post (as returned by the WP REST API).
	 *
	 * @param array $post Source post: id, date, title.rendered, content.rendered …
	 * @return array {
	 *   title, verse, book, date (Y-m-d H:i:s), start (Ymd), end (Ymd),
	 *   lesson_rows[], homework_rows[], content (leftover HTML), files[]
	 * }
	 */
	public static function parse( array $post ) {
		$title = self::clean_text( $post['title']['rendered'] ?? '' );
		$html  = $post['content']['rendered'] ?? '';
		$date  = str_replace( 'T', ' ', $post['date'] ?? '' );
		$year  = (int) substr( $date, 0, 4 );

		list( $verse, $book ) = self::detect_verse( $title );

		$files    = array();
		$leftover = '';
		self::split_content( $html, $files, $leftover );

		$lesson_rows   = array();
		$homework_rows = array();
		foreach ( $files as $f ) {
			$meta = self::parse_label( $f['label'], $title );
			if ( ! $meta['year'] && $year ) {
				$meta['year'] = $year;
			}
			$f = array_merge( $f, $meta );

			if ( 'homework' === $meta['kind'] ) {
				self::add_to_rows( $homework_rows, $f );
			} else {
				self::add_to_rows( $lesson_rows, $f );
			}
		}

		$start = $date ? gmdate( 'Ymd', strtotime( substr( $date, 0, 10 ) . ' 00:00:00 UTC' ) ) : '';
		$end   = $date ? gmdate( 'Ymd', strtotime( substr( $date, 0, 10 ) . ' 00:00:00 UTC +7 days' ) ) : '';

		return array(
			'title'         => $title,
			'verse'         => $verse,
			'book'          => $book,
			'date'          => $date,
			'start'         => $start,
			'end'           => $end,
			'lesson_rows'   => $lesson_rows,
			'homework_rows' => $homework_rows,
			'content'       => trim( $leftover ),
			'files'         => $files,
		);
	}

	/**
	 * Title → [verse, book]. A title is a verse when it starts with a book name
	 * followed by a chapter number, e.g. "Acts 6:1-15", "Psalms 44, 74".
	 */
	public static function detect_verse( $title ) {
		foreach ( self::BOOKS as $name => $canonical ) {
			$re = '/^' . str_replace( ' ', '\s+', preg_quote( $name, '/' ) ) . '\s+\d/i';
			if ( preg_match( $re, $title ) ) {
				return array( $title, $canonical );
			}
		}
		// Whole-book lessons: "Jude", "2 & 3 John".
		$plain = preg_replace( '/^\d\s*(?:&|and)\s*(?=\d)/i', '', $title );
		foreach ( self::BOOKS as $name => $canonical ) {
			if ( 0 === strcasecmp( preg_replace( '/\s+/', ' ', $plain ), $name ) ) {
				return array( $title, $canonical );
			}
		}
		return array( '', '' );
	}

	/**
	 * Split rendered content into document links (File blocks / links to
	 * .pdf/.doc/.docx) and everything else (kept as lesson content).
	 */
	private static function split_content( $html, array &$files, &$leftover ) {
		if ( '' === trim( $html ) ) {
			return;
		}
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="UTF-8"><div id="potw-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$root = $doc->getElementById( 'potw-root' );
		if ( ! $root ) {
			$leftover = $html;
			return;
		}

		$seen = array();
		foreach ( iterator_to_array( $root->childNodes ) as $node ) {
			if ( $node instanceof DOMElement && self::has_class( $node, 'wp-block-file' ) ) {
				$link = null;
				foreach ( $node->getElementsByTagName( 'a' ) as $a ) {
					if ( ! self::has_class( $a, 'wp-block-file__button' ) && $a->getAttribute( 'href' ) ) {
						$link = $a;
						break;
					}
				}
				if ( ! $link ) {
					foreach ( $node->getElementsByTagName( 'a' ) as $a ) {
						if ( $a->getAttribute( 'href' ) ) {
							$link = $a;
							break;
						}
					}
				}
				if ( $link ) {
					$url = trim( $link->getAttribute( 'href' ) );
					$ext = self::ext( $url );
					if ( in_array( $ext, self::DOC_EXT, true ) && ! isset( $seen[ $url ] ) ) {
						$seen[ $url ] = true;
						$files[]      = array(
							'url'   => $url,
							'ext'   => $ext,
							'label' => self::clean_text( $link->textContent ),
						);
						continue;
					}
				}
			}
			// Anything else stays as content (skip whitespace-only text and empty paragraphs).
			$out = $doc->saveHTML( $node );
			if ( '' !== trim( potw_li_strip_tags( $out ) ) || preg_match( '/<(img|iframe|a|figure|video|audio)\b/i', $out ) ) {
				$leftover .= $out . "\n";
			}
		}
	}

	/**
	 * Read kind / person / year from a file label like
	 * "POW Lesson #6 – Acts 6:1-15 – Lecture (Word) – Ellen Wong (2026)".
	 */
	public static function parse_label( $label, $title = '' ) {
		$t = trim( preg_replace( '/\s+/u', ' ', $label ) );
		$l = strtolower( $t );

		if ( preg_match( '/home\s*w|homwork|homeowkr|\bhw\b|work\s*sheet|worksheet/', $l ) ) {
			$kind = 'homework';
		} elseif ( false !== strpos( $l, 'devotion' ) ) {
			$kind = 'devotion';
		} elseif ( false !== strpos( $l, 'lecture' ) ) {
			$kind = 'lecture';
		} else {
			$kind = 'other';
		}

		$year = 0;
		$name = '';

		// Text after the "(Word)" / "(PDF)" marker holds "Person (Year)".
		if ( preg_match( '/\(\s*(?:word|pdf|pfd|docx?)\s*\)/iu', $t, $m, PREG_OFFSET_CAPTURE ) ) {
			$before = substr( $t, 0, $m[0][1] );
			$after  = substr( $t, $m[0][1] + strlen( $m[0][0] ) );
			$after  = preg_replace( '/^[\s\x{2013}\x{2014}\-:]+/u', '', $after );

			if ( preg_match( '/\(\s*((?:19|20)\d{2})\s*\)/', $after, $ym ) || preg_match( '/\b((?:19|20)\d{2})\b/', $after, $ym ) ) {
				$year = (int) $ym[1];
			}
			$name = preg_replace( '/\b(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\.?\s+\d{1,2}(?:st|nd|rd|th)?\b,?/i', '', $after );
			$name = preg_replace( '/\(\s*(?:19|20)\d{2}\s*\)|\b(?:19|20)\d{2}\b/', '', $name );
			$name = self::trim_dashes( $name );

			if ( 'lecture' !== $kind && 'homework' !== $kind ) {
				// Devotions / other docs: describe the document itself, e.g. "AM Devotion – Dan Leppo".
				$what = self::strip_prefix( $before, $title );
				$name = self::trim_dashes( $what . ( $name ? ' – ' . $name : '' ) );
			}
		} else {
			// No format marker: "2025-2026 POW CALENDAR – PDF", "Bio -POLYCARP c.155", …
			if ( preg_match( '/\(\s*((?:19|20)\d{2})\s*\)\s*$/', $t, $ym ) ) {
				$year = (int) $ym[1];
			}
			$what = preg_replace( '/[\s\x{2013}\x{2014}\-]+(word|pdf|docx?)\s*$/iu', '', $t );
			$what = preg_replace( '/\(\s*(?:19|20)\d{2}\s*\)\s*$/', '', $what );
			$name = self::trim_dashes( self::strip_prefix( $what, $title ) );
			if ( '' === $name ) {
				$name = self::trim_dashes( $what );
			}
		}

		return array(
			'kind' => $kind,
			'name' => $name,
			'year' => $year,
		);
	}

	/** Pair Word + PDF of the same document into one repeater row. */
	private static function add_to_rows( array &$rows, array $f ) {
		$slot = ( 'pdf' === $f['ext'] ) ? 'pdf' : 'word';
		$key  = $f['kind'] . '|' . strtolower( preg_replace( '/[^a-z0-9]+/i', '', $f['name'] ) ) . '|' . $f['year'];

		foreach ( $rows as &$row ) {
			if ( $row['key'] === $key && empty( $row[ $slot ] ) ) {
				$row[ $slot ] = $f;
				return;
			}
		}
		unset( $row );

		// Same file name with the other extension (labels with typos, e.g. "Cheir Bean").
		$base = strtolower( preg_replace( '/\.[a-z0-9]+$/i', '', basename( (string) parse_url( $f['url'], PHP_URL_PATH ) ) ) );
		foreach ( $rows as &$row ) {
			$other = 'pdf' === $slot ? 'word' : 'pdf';
			if ( empty( $row[ $slot ] ) && ! empty( $row[ $other ] ) && ( $row['kind'] === $f['kind'] || 'homework' !== $f['kind'] && 'homework' !== $row['kind'] ) ) {
				$obase = strtolower( preg_replace( '/\.[a-z0-9]+$/i', '', basename( (string) parse_url( $row[ $other ]['url'], PHP_URL_PATH ) ) ) );
				if ( $obase === $base ) {
					$row[ $slot ] = $f;
					if ( '' === $row['name'] ) {
						$row['name'] = $f['name'];
					}
					return;
				}
			}
		}
		unset( $row );

		// Same document whose Word/PDF labels disagree on the year (typo on the old site).
		$loose = preg_replace( '/\|\d*$/', '|', $key );
		foreach ( $rows as &$row ) {
			$other = 'pdf' === $slot ? 'word' : 'pdf';
			if ( preg_replace( '/\|\d*$/', '|', $row['key'] ) === $loose && empty( $row[ $slot ] ) && ! empty( $row[ $other ] ) ) {
				$row[ $slot ] = $f;
				$row['year']  = max( (int) $row['year'], (int) $f['year'] );
				return;
			}
		}
		unset( $row );

		$rows[] = array(
			'key'  => $key,
			'kind' => $f['kind'],
			'name' => $f['name'],
			'year' => $f['year'],
			'pdf'  => 'pdf' === $slot ? $f : null,
			'word' => 'word' === $slot ? $f : null,
		);
	}

	/** Remove "POW Lesson #6 – Acts 6:1-15 –" style prefixes from a label part. */
	private static function strip_prefix( $s, $title ) {
		$s = preg_replace( '/^\s*(pow\s+)?lesson\s*#?\s*\d+\s*[\x{2013}\x{2014}\-:]*\s*/iu', '', $s );
		if ( $title ) {
			$norm_title = preg_replace( '/\s+/', '', strtolower( $title ) );
			$parts      = preg_split( '/\s+[\x{2013}\x{2014}\-]\s*|\s*[\x{2013}\x{2014}]\s*/u', $s );
			$parts      = array_values(
				array_filter(
					$parts,
					function ( $p ) use ( $norm_title ) {
						$n = preg_replace( '/\s+/', '', strtolower( trim( $p ) ) );
						return '' !== $n && $n !== $norm_title;
					}
				)
			);
			$s = implode( ' – ', array_map( 'trim', $parts ) );
		}
		return $s;
	}

	private static function trim_dashes( $s ) {
		return trim( preg_replace( '/^[\s\x{2013}\x{2014}\-:]+|[\s\x{2013}\x{2014}\-:]+$/u', '', $s ) );
	}

	public static function clean_text( $s ) {
		$s = html_entity_decode( (string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$s = str_replace( "\xC2\xA0", ' ', $s );
		return trim( preg_replace( '/\s+/u', ' ', $s ) );
	}

	public static function ext( $url ) {
		$path = (string) parse_url( $url, PHP_URL_PATH );
		return strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	}

	/**
	 * Stable key for a source upload, independent of which domain linked it:
	 * "https://peopleoftheword.online/wp-content/uploads/2026/10/x.pdf" → "2026/10/x.pdf".
	 */
	public static function source_key( $url ) {
		$path = rawurldecode( (string) parse_url( $url, PHP_URL_PATH ) );
		if ( preg_match( '#/wp-content/uploads/(.+)$#', $path, $m ) ) {
			return $m[1];
		}
		return ltrim( $path, '/' );
	}

	private static function has_class( DOMElement $el, $class ) {
		return in_array( $class, preg_split( '/\s+/', (string) $el->getAttribute( 'class' ) ), true );
	}
}

/** strip tags without needing WordPress loaded. */
if ( ! function_exists( 'potw_li_strip_tags' ) ) {
function potw_li_strip_tags( $s ) {
	return trim( html_entity_decode( strip_tags( $s ), ENT_QUOTES, 'UTF-8' ), " \t\n\r\0\x0B\xC2\xA0" );
}
}

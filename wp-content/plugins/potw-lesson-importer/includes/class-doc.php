<?php
/**
 * Minimal reader for old Word 97–2003 .doc files: pulls the main document text
 * out of the OLE compound file (WordDocument + 0Table/1Table piece table).
 * Formatting is not available, so the result is plain paragraphs that are then
 * handed to POTW_LI_Docx::from_plain_paragraphs() (ALL-CAPS lines → headings).
 *
 * Word 6/95 files and encrypted files are not supported.
 *
 * @package potw-lesson-importer
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'POTW_LI_TESTING' ) ) {
	exit;
}

class POTW_LI_Doc {

	/**
	 * @return array|WP_Error Same shape as POTW_LI_Docx::convert().
	 */
	public static function convert( $path, array $context = array() ) {
		$paras = self::paragraphs( $path );
		if ( is_wp_error( $paras ) ) {
			return $paras;
		}
		return POTW_LI_Docx::from_plain_paragraphs( $paras, $context );
	}

	/**
	 * @return string[]|WP_Error
	 */
	public static function paragraphs( $path ) {
		$data = @file_get_contents( $path ); // phpcs:ignore
		if ( false === $data || strlen( $data ) < 1536 ) {
			return new WP_Error( 'potw_doc', 'Unreadable .doc' );
		}
		$cfb = self::cfb_streams( $data );
		if ( is_wp_error( $cfb ) ) {
			return $cfb;
		}
		if ( empty( $cfb['WordDocument'] ) ) {
			return new WP_Error( 'potw_doc', 'Not a Word document (no WordDocument stream)' );
		}
		$wd = $cfb['WordDocument'];

		if ( 0xA5EC !== self::u16( $wd, 0 ) ) {
			return new WP_Error( 'potw_doc', 'Unknown Word format' );
		}
		$nfib  = self::u16( $wd, 2 );
		$flags = self::u16( $wd, 0x0A );
		if ( $nfib < 0x00C1 ) {
			return new WP_Error( 'potw_doc', 'Word 6/95 format is not supported' );
		}
		if ( $flags & 0x0100 ) {
			return new WP_Error( 'potw_doc', 'Encrypted document' );
		}
		$table_name = ( $flags & 0x0200 ) ? '1Table' : '0Table';
		$table      = $cfb[ $table_name ] ?? '';
		if ( '' === $table ) {
			return new WP_Error( 'potw_doc', 'Missing table stream' );
		}

		$ccp_text = self::u32( $wd, 0x4C );
		$fc_clx   = self::u32( $wd, 0x01A2 );
		$lcb_clx  = self::u32( $wd, 0x01A6 );
		if ( ! $lcb_clx || $fc_clx + $lcb_clx > strlen( $table ) ) {
			return new WP_Error( 'potw_doc', 'No piece table' );
		}

		// Skip Prc entries, find Pcdt.
		$pos = $fc_clx;
		$end = $fc_clx + $lcb_clx;
		while ( $pos < $end && 0x01 === ord( $table[ $pos ] ) ) {
			$pos += 3 + self::u16( $table, $pos + 1 );
		}
		if ( $pos >= $end || 0x02 !== ord( $table[ $pos ] ) ) {
			return new WP_Error( 'potw_doc', 'Bad piece table' );
		}
		$lcb = self::u32( $table, $pos + 1 );
		$plc = $pos + 5;
		$n   = (int) ( ( $lcb - 4 ) / 12 );

		$text = '';
		for ( $i = 0; $i < $n && mb_strlen( $text, 'UTF-8' ) < $ccp_text + 16; $i++ ) {
			$cp_start = self::u32( $table, $plc + $i * 4 );
			$cp_end   = self::u32( $table, $plc + ( $i + 1 ) * 4 );
			$pcd      = $plc + ( $n + 1 ) * 4 + $i * 8;
			$fc       = self::u32( $table, $pcd + 2 );
			$chars    = max( 0, $cp_end - $cp_start );
			if ( $cp_start >= $ccp_text ) {
				break;
			}
			$chars = min( $chars, $ccp_text - $cp_start );

			if ( $fc & 0x40000000 ) {
				$off   = ( $fc & 0x3FFFFFFF ) >> 1;
				$raw   = substr( $wd, $off, $chars );
				$text .= self::cp1252( $raw );
			} else {
				$raw   = substr( $wd, $fc, $chars * 2 );
				$text .= mb_convert_encoding( $raw, 'UTF-8', 'UTF-16LE' );
			}
		}

		return self::split( $text );
	}

	/** Turn Word's control characters into paragraphs. */
	private static function split( $text ) {
		// Field codes: \x13 code \x14 result \x15  → keep the result only.
		$text = preg_replace( '/\x13[^\x13\x14\x15]*\x14/u', '', $text );
		$text = preg_replace( '/\x13[^\x13\x14\x15]*\x15/u', '', $text );
		$text = str_replace( array( "\x14", "\x15", "\x13" ), '', $text );
		$text = strtr(
			$text,
			array(
				"\x0B" => "\n",   // line break.
				"\x0C" => "\r",   // page / section break.
				"\x07" => "\r",   // table cell / row end.
				"\x1E" => '-',    // non-breaking hyphen.
				"\x1F" => '',     // soft hyphen.
				"\x01" => '',     // picture.
				"\x08" => '',     // drawn object.
				"\x02" => '',     // footnote reference.
				"\x05" => '',     // annotation reference.
				"\t"   => ' ',
			)
		);
		$text = preg_replace( '/[\x00-\x09\x0E-\x1F]/', '', $text );
		$out  = array();
		foreach ( explode( "\r", $text ) as $p ) {
			$p = trim( $p );
			if ( '' !== $p ) {
				$out[] = $p;
			}
		}
		return $out;
	}

	private static function cp1252( $s ) {
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$r = @mb_convert_encoding( $s, 'UTF-8', 'Windows-1252' ); // phpcs:ignore
			if ( false !== $r ) {
				return $r;
			}
		}
		return utf8_encode( $s ); // phpcs:ignore
	}

	/* ------------------------------------------------------------------ */
	/* Compound File Binary (OLE2)                                         */
	/* ------------------------------------------------------------------ */

	/** @return array|WP_Error name => stream bytes (only the streams we need). */
	private static function cfb_streams( $d ) {
		if ( substr( $d, 0, 8 ) !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" ) {
			return new WP_Error( 'potw_doc', 'Not an OLE .doc file' );
		}
		$sector_size = 1 << self::u16( $d, 0x1E );
		$mini_size   = 1 << self::u16( $d, 0x20 );
		$num_fat     = self::u32( $d, 0x2C );
		$dir_start   = self::u32( $d, 0x30 );
		$mini_cutoff = self::u32( $d, 0x38 );
		$minifat_st  = self::u32( $d, 0x3C );
		$difat_st    = self::u32( $d, 0x44 );
		$num_difat   = self::u32( $d, 0x48 );

		$sector = function ( $id ) use ( $d, $sector_size ) {
			return substr( $d, ( $id + 1 ) * $sector_size, $sector_size );
		};

		// FAT sector list from DIFAT.
		$fat_sectors = array();
		for ( $i = 0; $i < 109 && count( $fat_sectors ) < $num_fat; $i++ ) {
			$fat_sectors[] = self::u32( $d, 0x4C + $i * 4 );
		}
		$next  = $difat_st;
		$guard = 0;
		while ( count( $fat_sectors ) < $num_fat && $next < 0xFFFFFFFA && $guard++ <= $num_difat + 1 ) {
			$s   = $sector( $next );
			$per = $sector_size / 4 - 1;
			for ( $i = 0; $i < $per && count( $fat_sectors ) < $num_fat; $i++ ) {
				$fat_sectors[] = self::u32( $s, $i * 4 );
			}
			$next = self::u32( $s, $per * 4 );
		}
		$fat = '';
		foreach ( $fat_sectors as $fs ) {
			$fat .= $sector( $fs );
		}
		$fat_count = strlen( $fat ) >> 2;

		$chain = function ( $start, $table, $count ) {
			$ids   = array();
			$id    = $start;
			$guard = 0;
			while ( $id < 0xFFFFFFFA && $id < $count && $guard++ < $count ) {
				$ids[] = $id;
				$id    = self::u32( $table, $id * 4 );
			}
			return $ids;
		};
		$read = function ( $start ) use ( $chain, $fat, $fat_count, $sector ) {
			$out = '';
			foreach ( $chain( $start, $fat, $fat_count ) as $id ) {
				$out .= $sector( $id );
			}
			return $out;
		};

		$dir = $read( $dir_start );

		$minifat = $minifat_st < 0xFFFFFFFA ? $read( $minifat_st ) : '';
		$mini_ct = strlen( $minifat ) >> 2;

		$entries = array();
		$root    = null;
		for ( $o = 0; $o + 128 <= strlen( $dir ); $o += 128 ) {
			$name_len = self::u16( $dir, $o + 0x40 );
			if ( $name_len < 2 ) {
				continue;
			}
			$name = mb_convert_encoding( substr( $dir, $o, $name_len - 2 ), 'UTF-8', 'UTF-16LE' );
			$type = ord( $dir[ $o + 0x42 ] );
			$e    = array(
				'type'  => $type,
				'start' => self::u32( $dir, $o + 0x74 ),
				'size'  => self::u32( $dir, $o + 0x78 ),
			);
			if ( 5 === $type ) {
				$root = $e;
			} elseif ( 2 === $type ) {
				$entries[ $name ] = $e;
			}
		}

		$ministream = $root ? $read( $root['start'] ) : '';
		$out        = array();
		foreach ( array( 'WordDocument', '0Table', '1Table' ) as $want ) {
			if ( ! isset( $entries[ $want ] ) ) {
				continue;
			}
			$e = $entries[ $want ];
			if ( $e['size'] < $mini_cutoff && '' !== $ministream ) {
				$buf = '';
				foreach ( $chain( $e['start'], $minifat, $mini_ct ) as $id ) {
					$buf .= substr( $ministream, $id * $mini_size, $mini_size );
				}
			} else {
				$buf = $read( $e['start'] );
			}
			$out[ $want ] = substr( $buf, 0, $e['size'] );
		}
		return $out;
	}

	private static function u16( $s, $o ) {
		$v = unpack( 'v', substr( $s, $o, 2 ) );
		return $v ? $v[1] : 0;
	}

	private static function u32( $s, $o ) {
		$v = unpack( 'V', substr( $s, $o, 4 ) );
		return $v ? $v[1] : 0;
	}
}

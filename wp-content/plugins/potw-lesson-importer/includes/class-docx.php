<?php
/**
 * Converts a lecture .docx into Gutenberg block markup.
 *
 * - Word heading styles → h2 / h3.
 * - Otherwise a short line that is entirely bold, underlined or ALL CAPS is a
 *   heading (ALL CAPS is turned into Title Case). If a document uses two such
 *   styles, the one that appears first is h2 and the other h3.
 * - The header block at the top (title / verse / lecturer name) is stripped;
 *   the title is returned separately.
 * - Lists (with nesting), tables, bold / italic / underline and links are kept.
 *
 * @package potw-lesson-importer
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'POTW_LI_TESTING' ) ) {
	exit;
}

class POTW_LI_Docx {

	const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
	const R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

	/** @var DOMXPath */
	private $xp;
	private $styles   = array(); // styleId => [name, bold, outline]
	private $numfmt   = array(); // numId|ilvl => numFmt
	private $rels     = array(); // rId => target
	private $warnings = array();
	private $plain    = false; // true for .doc text (no formatting available).
	private $lecturer = '';    // normalised lecturer name — never a heading.

	/**
	 * Section names lecturers use again and again. A short line that is just
	 * one of these is always a heading, even without bold/caps (old .doc files).
	 */
	const KEYWORD_HEADINGS = '/^(introduction|intro|background|overview|application|applications|personal application|life application|conclusion|in conclusion|summary|review|action steps?|takeaways?|key points?|let\x{2019}?\'?s pray|prayer|closing( prayer| thoughts| remarks)?|q\s*&\s*a|questions?( (for|to) (discussion|reflection|ponder|consider))?|discussion questions|references|sources|notes|end\\s*notes|memory verse|key verse)\s*[:.!]?$/iu';

	/** Headings that introduce the closing prayer. */
	const PRAYER_HEADING = '/^(let\x{2019}?\'?s pray|let us pray|prayer|closing prayer|a prayer|our prayer|prayer of [a-z ]+)\s*[:.!]?$/iu';

	/** "Part 1 – …", "Point 2: …", "Section III". */
	const KEYWORD_PREFIX = '/^(part|point|section)\s+(\d+|[ivx]+|one|two|three|four|five|six|seven)\b/iu';

	/**
	 * @param string $path    Local .docx path.
	 * @param array  $context verse, lecturer (used to strip the header block).
	 * @return array|WP_Error { blocks, title, headings, paragraphs, warnings }
	 */
	public static function convert( $path, array $context = array() ) {
		$self  = new self();
		$parts = $self->read_zip( $path, array( 'word/document.xml', 'word/styles.xml', 'word/numbering.xml', 'word/_rels/document.xml.rels' ) );
		if ( empty( $parts['word/document.xml'] ) ) {
			return new WP_Error( 'potw_docx', 'Not a readable .docx: ' . basename( $path ) );
		}
		$self->load_styles( $parts['word/styles.xml'] ?? '' );
		$self->load_numbering( $parts['word/numbering.xml'] ?? '' );
		$self->load_rels( $parts['word/_rels/document.xml.rels'] ?? '' );

		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$ok = $doc->loadXML( $parts['word/document.xml'], LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE );
		libxml_clear_errors();
		if ( ! $ok ) {
			return new WP_Error( 'potw_docx', 'Could not parse ' . basename( $path ) );
		}
		$self->xp = new DOMXPath( $doc );
		$self->xp->registerNamespace( 'w', self::W );

		$body = $self->xp->query( '/w:document/w:body' )->item( 0 );
		if ( ! $body ) {
			return new WP_Error( 'potw_docx', 'Empty document: ' . basename( $path ) );
		}

		$items = array();
		$self->collect( $body, $items );

		return $self->finish( $items, $context );
	}

	/**
	 * Build blocks from plain-text paragraphs (used for old .doc files, which
	 * only give us text). ALL-CAPS lines can still become headings, and lines
	 * starting with a bullet character become list items.
	 *
	 * @param string[] $paras
	 */
	public static function from_plain_paragraphs( array $paras, array $context = array() ) {
		$self        = new self();
		$self->plain = true;
		$items       = array();
		foreach ( $paras as $t ) {
			$t = trim( preg_replace( '/[ \t\x{00A0}]+/u', ' ', $t ) );
			if ( '' === $t ) {
				continue;
			}
			$list = null;
			if ( preg_match( '/^[\x{2022}\x{00B7}\x{25AA}\x{25CF}\x{F0B7}\x{F0A7}\x{2013}\-\*o§]\s+(.+)$/u', $t, $m ) ) {
				$t    = $m[1];
				$list = array(
					'level'   => 0,
					'ordered' => false,
					'fmt'     => 'bullet',
				);
			}
			$items[] = array(
				'type'     => 'p',
				'plain'    => $t,
				'html'     => nl2br( htmlspecialchars( $t, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), false ),
				'style'    => '',
				'outline'  => null,
				'list'     => $list,
				'center'   => false,
				'all_bold' => false,
				'all_u'    => false,
			);
		}
		return $self->finish( $items, $context );
	}

	private function finish( array $items, array $context ) {
		$self           = $this;
		$this->lecturer = self::norm( preg_replace( '/^(dr|rev|pastor|mr|mrs|ms)\.?\s+/i', '', (string) ( $context['lecturer'] ?? '' ) ) );
		list( $items, $title ) = $self->strip_header( $items, $context );
		$items                 = $self->detect_headings( $items );
		$items                 = $self->refine_headings( $items, $context, $title );
		$items                 = $self->mark_quotes_and_prayers( $items );

		$headings = count(
			array_filter(
				$items,
				function ( $i ) {
					return 'heading' === $i['type'];
				}
			)
		);

		return array(
			'blocks'     => $self->render( $items ),
			'title'      => $title,
			'headings'   => $headings,
			'paragraphs' => count( $items ),
			'warnings'   => $self->warnings,
		);
	}

	/* ------------------------------------------------------------------ */
	/* Reading                                                             */
	/* ------------------------------------------------------------------ */

	private function read_zip( $path, array $names ) {
		$out = array();
		if ( class_exists( 'ZipArchive' ) ) {
			$zip = new ZipArchive();
			if ( true === $zip->open( $path ) ) {
				foreach ( $names as $n ) {
					$c = $zip->getFromName( $n );
					if ( false !== $c ) {
						$out[ $n ] = $c;
					}
				}
				$zip->close();
			}
			return $out;
		}
		if ( defined( 'ABSPATH' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
			$zip  = new PclZip( $path );
			$list = $zip->extract( PCLZIP_OPT_BY_NAME, $names, PCLZIP_OPT_EXTRACT_AS_STRING );
			foreach ( (array) $list as $f ) {
				if ( isset( $f['filename'], $f['content'] ) ) {
					$out[ $f['filename'] ] = $f['content'];
				}
			}
		}
		return $out;
	}

	private function xml_xpath( $xml ) {
		if ( '' === trim( (string) $xml ) ) {
			return null;
		}
		$d = new DOMDocument();
		libxml_use_internal_errors( true );
		$ok = $d->loadXML( $xml, LIBXML_NONET | LIBXML_COMPACT );
		libxml_clear_errors();
		if ( ! $ok ) {
			return null;
		}
		$xp = new DOMXPath( $d );
		$xp->registerNamespace( 'w', self::W );
		return $xp;
	}

	private function load_styles( $xml ) {
		$xp = $this->xml_xpath( $xml );
		if ( ! $xp ) {
			return;
		}
		foreach ( $xp->query( '//w:style' ) as $s ) {
			$id   = $s->getAttributeNS( self::W, 'styleId' );
			$name = $xp->query( 'w:name', $s )->item( 0 );
			$b    = $xp->query( 'w:rPr/w:b', $s )->item( 0 );
			$ol   = $xp->query( 'w:pPr/w:outlineLvl', $s )->item( 0 );

			$this->styles[ $id ] = array(
				'name'    => strtolower( $name ? $name->getAttributeNS( self::W, 'val' ) : $id ),
				'bold'    => $b && ! $this->is_off( $b ),
				'outline' => $ol ? (int) $ol->getAttributeNS( self::W, 'val' ) : null,
			);
		}
	}

	private function load_numbering( $xml ) {
		$xp = $this->xml_xpath( $xml );
		if ( ! $xp ) {
			return;
		}
		$abstract = array();
		foreach ( $xp->query( '//w:abstractNum' ) as $a ) {
			$aid = $a->getAttributeNS( self::W, 'abstractNumId' );
			foreach ( $xp->query( 'w:lvl', $a ) as $lvl ) {
				$f = $xp->query( 'w:numFmt', $lvl )->item( 0 );
				$abstract[ $aid ][ (int) $lvl->getAttributeNS( self::W, 'ilvl' ) ] = $f ? $f->getAttributeNS( self::W, 'val' ) : 'decimal';
			}
		}
		foreach ( $xp->query( '//w:num' ) as $n ) {
			$nid = $n->getAttributeNS( self::W, 'numId' );
			$a   = $xp->query( 'w:abstractNumId', $n )->item( 0 );
			$aid = $a ? $a->getAttributeNS( self::W, 'val' ) : null;
			foreach ( (array) ( $abstract[ $aid ] ?? array() ) as $ilvl => $fmt ) {
				$this->numfmt[ $nid . '|' . $ilvl ] = $fmt;
			}
		}
	}

	private function load_rels( $xml ) {
		if ( preg_match_all( '/<Relationship\b[^>]*>/', (string) $xml, $m ) ) {
			foreach ( $m[0] as $tag ) {
				if ( preg_match( '/\bId="([^"]+)"/', $tag, $id ) && preg_match( '/\bTarget="([^"]+)"/', $tag, $t ) && false !== strpos( $tag, 'hyperlink' ) ) {
					$this->rels[ $id[1] ] = html_entity_decode( $t[1], ENT_QUOTES | ENT_XML1, 'UTF-8' );
				}
			}
		}
	}

	/** Walk the body: paragraphs, tables, and content controls. */
	private function collect( DOMNode $node, array &$items ) {
		foreach ( $node->childNodes as $child ) {
			if ( XML_ELEMENT_NODE !== $child->nodeType || self::W !== $child->namespaceURI ) {
				continue;
			}
			switch ( $child->localName ) {
				case 'p':
					$p = $this->paragraph( $child );
					if ( $p ) {
						$items[] = $p;
					}
					break;
				case 'tbl':
					$t = $this->table( $child );
					if ( $t ) {
						$items[] = $t;
					}
					break;
				case 'sdt':
					$c = $this->xp->query( 'w:sdtContent', $child )->item( 0 );
					if ( $c ) {
						$this->collect( $c, $items );
					}
					break;
			}
		}
	}

	private function paragraph( DOMElement $p ) {
		$style_id = '';
		$ps       = $this->xp->query( 'w:pPr/w:pStyle', $p )->item( 0 );
		if ( $ps ) {
			$style_id = $ps->getAttributeNS( self::W, 'val' );
		}
		$style   = $this->styles[ $style_id ] ?? array(
			'name'    => strtolower( $style_id ),
			'bold'    => false,
			'outline' => null,
		);
		$ol      = $this->xp->query( 'w:pPr/w:outlineLvl', $p )->item( 0 );
		$outline = $ol ? (int) $ol->getAttributeNS( self::W, 'val' ) : $style['outline'];

		$list = null;
		$num  = $this->xp->query( 'w:pPr/w:numPr', $p )->item( 0 );
		if ( $num ) {
			$nid  = $this->xp->query( 'w:numId', $num )->item( 0 );
			$ilv  = $this->xp->query( 'w:ilvl', $num )->item( 0 );
			$nid  = $nid ? $nid->getAttributeNS( self::W, 'val' ) : '0';
			$ilv  = $ilv ? (int) $ilv->getAttributeNS( self::W, 'val' ) : 0;
			$fmt  = $this->numfmt[ $nid . '|' . $ilv ] ?? 'bullet';
			$list = '0' === $nid ? null : array(
				'level'   => min( $ilv, 4 ),
				'ordered' => ! in_array( $fmt, array( 'bullet', 'none' ), true ),
				'fmt'     => $fmt,
			);
		}

		$jc     = $this->xp->query( 'w:pPr/w:jc', $p )->item( 0 );
		$center = $jc && 'center' === $jc->getAttributeNS( self::W, 'val' );
		$ind    = $this->xp->query( 'w:pPr/w:ind', $p )->item( 0 );
		$indent = 0;
		if ( $ind ) {
			$left   = $ind->getAttributeNS( self::W, 'left' );
			$left   = '' !== $left ? $left : $ind->getAttributeNS( self::W, 'start' );
			$indent = (int) $left;
		}

		$segments = array();
		$this->runs( $p, $segments, null, $style['bold'] );

		$html  = '';
		$plain = '';
		$chars = 0;
		$bold  = 0;
		$under = 0;
		$ital  = 0;
		foreach ( $segments as $s ) {
			if ( $s['i'] ) {
				$ital += function_exists( 'mb_strlen' ) ? mb_strlen( trim( $s['text'] ) ) : strlen( trim( $s['text'] ) );
			}
			$plain .= $s['text'];
			$len    = function_exists( 'mb_strlen' ) ? mb_strlen( trim( $s['text'] ) ) : strlen( trim( $s['text'] ) );
			$chars += $len;
			if ( $s['b'] ) {
				$bold += $len;
			}
			if ( $s['u'] ) {
				$under += $len;
			}
			$html .= $this->segment_html( $s );
		}
		$plain = trim( preg_replace( '/\s+/u', ' ', str_replace( "\n", ' ', $plain ) ) );
		if ( '' === $plain ) {
			return null;
		}

		return array(
			'type'     => 'p',
			'plain'    => $plain,
			'html'     => $this->tidy_html( $html ),
			'style'    => $style['name'],
			'outline'  => $outline,
			'list'     => $list,
			'center'   => $center,
			'all_bold' => $chars > 0 && $bold >= $chars,
			'all_u'    => $chars > 0 && $under >= $chars,
			'all_i'    => $chars > 0 && $ital >= $chars,
			'indent'   => $indent,
		);
	}

	/** Collect text runs (recurses into hyperlinks, insertions, smart tags, fields). */
	private function runs( DOMNode $node, array &$out, $href, $style_bold ) {
		foreach ( $node->childNodes as $c ) {
			if ( XML_ELEMENT_NODE !== $c->nodeType || self::W !== $c->namespaceURI ) {
				continue;
			}
			switch ( $c->localName ) {
				case 'r':
					$this->run( $c, $out, $href, $style_bold );
					break;
				case 'hyperlink':
					$rid = $c->getAttributeNS( self::R, 'id' );
					$this->runs( $c, $out, $rid && isset( $this->rels[ $rid ] ) ? $this->rels[ $rid ] : $href, $style_bold );
					break;
				case 'ins':
				case 'smartTag':
				case 'fldSimple':
				case 'customXml':
				case 'sdtContent':
					$this->runs( $c, $out, $href, $style_bold );
					break;
				case 'sdt':
					$this->runs( $c, $out, $href, $style_bold );
					break;
			}
		}
	}

	private function run( DOMElement $r, array &$out, $href, $style_bold ) {
		$pr   = $this->xp->query( 'w:rPr', $r )->item( 0 );
		$flag = function ( $name ) use ( $pr ) {
			if ( ! $pr ) {
				return null;
			}
			$e = $this->xp->query( 'w:' . $name, $pr )->item( 0 );
			return $e ? ! $this->is_off( $e ) : null;
		};
		$rstyle = '';
		if ( $pr ) {
			$rs = $this->xp->query( 'w:rStyle', $pr )->item( 0 );
			if ( $rs ) {
				$id     = $rs->getAttributeNS( self::W, 'val' );
				$rstyle = $this->styles[ $id ]['name'] ?? strtolower( $id );
			}
		}

		$b = $flag( 'b' );
		$b = null === $b ? ( $style_bold || false !== strpos( $rstyle, 'strong' ) || ( $this->styles_bold( $rstyle ) ) ) : $b;
		$i = $flag( 'i' );
		$i = null === $i ? false !== strpos( $rstyle, 'emphasis' ) : $i;
		$u = false;
		if ( $pr ) {
			$ue = $this->xp->query( 'w:u', $pr )->item( 0 );
			$u  = $ue && ! in_array( $ue->getAttributeNS( self::W, 'val' ), array( 'none', '0', 'false' ), true );
		}
		$caps = (bool) $flag( 'caps' );
		$vert = '';
		if ( $pr ) {
			$va   = $this->xp->query( 'w:vertAlign', $pr )->item( 0 );
			$vert = $va ? $va->getAttributeNS( self::W, 'val' ) : '';
		}

		$text = '';
		foreach ( $r->childNodes as $c ) {
			if ( XML_ELEMENT_NODE !== $c->nodeType ) {
				continue;
			}
			switch ( $c->localName ) {
				case 't':
					$text .= $c->textContent;
					break;
				case 'tab':
				case 'ptab':
					$text .= ' ';
					break;
				case 'br':
				case 'cr':
					$type = $c->getAttributeNS( self::W, 'type' );
					if ( 'page' !== $type && 'column' !== $type ) {
						$text .= "\n";
					}
					break;
				case 'noBreakHyphen':
					$text .= '-';
					break;
				case 'softHyphen':
					break;
			}
		}
		if ( '' === $text ) {
			return;
		}
		if ( $caps ) {
			$text = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $text ) : strtoupper( $text );
		}
		$out[] = array(
			'text' => $text,
			'b'    => (bool) $b,
			'i'    => (bool) $i,
			'u'    => (bool) $u,
			'sup'  => 'superscript' === $vert,
			'sub'  => 'subscript' === $vert,
			'href' => $href,
		);
	}

	private function styles_bold( $name ) {
		foreach ( $this->styles as $s ) {
			if ( $s['name'] === $name ) {
				return $s['bold'];
			}
		}
		return false;
	}

	private function is_off( DOMElement $e ) {
		$v = $e->getAttributeNS( self::W, 'val' );
		return in_array( $v, array( '0', 'false', 'off', 'none' ), true );
	}

	private function segment_html( array $s ) {
		$h = nl2br( htmlspecialchars( $s['text'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ), false );
		if ( $s['sup'] ) {
			$h = '<sup>' . $h . '</sup>';
		}
		if ( $s['sub'] ) {
			$h = '<sub>' . $h . '</sub>';
		}
		if ( $s['u'] && ! $s['href'] ) {
			$h = '<u>' . $h . '</u>';
		}
		if ( $s['i'] ) {
			$h = '<em>' . $h . '</em>';
		}
		if ( $s['b'] ) {
			$h = '<strong>' . $h . '</strong>';
		}
		if ( $s['href'] && preg_match( '#^(https?:|mailto:)#i', $s['href'] ) ) {
			$h = '<a href="' . htmlspecialchars( $s['href'], ENT_QUOTES, 'UTF-8' ) . '">' . $h . '</a>';
		}
		return $h;
	}

	/** Merge adjacent identical tags (<strong>a</strong><strong>b</strong>) and trim. */
	private function tidy_html( $h ) {
		for ( $n = 0; $n < 3; $n++ ) {
			$h = preg_replace( '#</(strong|em|u|sup|sub)>(\s*)<\1>#', '$2', $h );
		}
		$h = preg_replace( '#<(strong|em|u)>(\s*)</\1>#', '$2', $h );
		$h = preg_replace( '#^(\s|<br>)+|(\s|<br>)+$#', '', $h );
		return trim( $h );
	}

	private function table( DOMElement $tbl ) {
		$rows = array();
		foreach ( $this->xp->query( 'w:tr', $tbl ) as $tr ) {
			$cells = array();
			foreach ( $this->xp->query( 'w:tc', $tr ) as $tc ) {
				$parts = array();
				foreach ( $this->xp->query( './/w:p', $tc ) as $p ) {
					$pp = $this->paragraph( $p );
					if ( $pp ) {
						$parts[] = $pp['html'];
					}
				}
				$cells[] = implode( '<br>', $parts );
			}
			if ( array_filter( $cells, 'strlen' ) ) {
				$rows[] = $cells;
			}
		}
		if ( ! $rows ) {
			return null;
		}
		// A one-cell table is just a boxed paragraph.
		if ( 1 === count( $rows ) && 1 === count( $rows[0] ) ) {
			return array(
				'type'     => 'p',
				'plain'    => trim( potw_li_strip_tags_docx( $rows[0][0] ) ),
				'html'     => $rows[0][0],
				'style'    => '',
				'outline'  => null,
				'list'     => null,
				'center'   => false,
				'all_bold' => false,
				'all_u'    => false,
			);
		}
		return array(
			'type' => 'table',
			'rows' => $rows,
		);
	}

	/* ------------------------------------------------------------------ */
	/* Structure                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Remove the title / verse / lecturer lines at the top. Returns the title.
	 */
	private function strip_header( array $items, array $ctx ) {
		$title    = '';
		$verse    = self::norm( $ctx['verse'] ?? '' );
		$lecturer = self::norm( $ctx['lecturer'] ?? '' );
		$last     = '';
		if ( $lecturer ) {
			$bits = explode( ' ', trim( preg_replace( '/[^a-z ]/', '', strtolower( $ctx['lecturer'] ) ) ) );
			$last = end( $bits );
		}

		$i = 0;
		while ( $i < count( $items ) && $i < 7 ) {
			$it = $items[ $i ];
			if ( 'p' !== $it['type'] || $it['list'] ) {
				break;
			}
			$t   = $it['plain'];
			$n   = self::norm( $t );
			$len = strlen( $t );
			if ( $len > 140 ) {
				break;
			}

			$is_verse = ( $verse && ( $n === $verse || 0 === strpos( $n, $verse ) || 0 === strpos( $verse, $n ) ) )
				|| preg_match( '/^(\d\s*)?[a-z]+\.?\s+\d+(:\d+)?\s*([-\x{2013}\x{2014},;]\s*\d+(:\d+)?)*\s*(\(.*\))?$/iu', $t );
			$is_name  = ( $lecturer && ( $n === $lecturer || ( $last && strlen( $last ) > 2 && false !== strpos( strtolower( $t ), $last ) && $len < 60 ) ) )
				|| preg_match( '/^(by|lecture by|presented by|written by)\s/i', $t );
			$is_label = preg_match( '/^(pow|people of the word|lesson\s*#?\s*\d+|lecture|lecture notes|\d{4})\b[^.]{0,60}$/i', $t ) && $len < 70;
			$is_date  = preg_match( '/^(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\.?\s+\d{1,2},?\s+\d{4}$/i', $t );

			if ( $is_verse || $is_name || $is_label || $is_date ) {
				$i++;
				continue;
			}
			// First other short line at the top = title (once).
			if ( '' === $title && $len <= 120 && ( $it['center'] || $it['all_bold'] || preg_match( '/^["\x{201C}\x{2018}\']/u', $t ) || $i < 2 ) && ! preg_match( '/[.!?]["\x{201D}]?$/u', $t ) ) {
				$title = trim( preg_replace( '/^["\x{201C}\x{2018}\']+|["\x{201D}\x{2019}\']+$/u', '', trim( $t ) ) );
				$i++;
				continue;
			}
			break;
		}
		return array( array_slice( $items, $i ), $title );
	}

	private function detect_headings( array $items ) {
		$has_word_headings = false;
		foreach ( $items as $it ) {
			if ( 'p' === $it['type'] && $this->word_heading_level( $it ) ) {
				$has_word_headings = true;
				break;
			}
		}

		// First pass: find candidates and their "signature".
		$sig_order = array();
		foreach ( $items as $k => $it ) {
			if ( 'p' !== $it['type'] ) {
				continue;
			}
			$lvl = $this->word_heading_level( $it );
			if ( $lvl ) {
				$items[ $k ]['type']  = 'heading';
				$items[ $k ]['level'] = $lvl;
				continue;
			}
			if ( $this->is_lecturer_line( $it['plain'] ) ) {
				continue;
			}
			$sig = $this->keyword_signature( $it );
			$sig = $sig ? $sig : $this->heuristic_signature( $it );
			if ( ! $sig && $this->plain ) {
				$sig = $this->plain_signature( $it, $items[ $k - 1 ] ?? null, $items[ $k + 1 ] ?? null );
			}
			if ( $sig ) {
				$items[ $k ]['sig'] = $sig;
				if ( ! in_array( $sig, $sig_order, true ) ) {
					$sig_order[] = $sig;
				}
			}
		}

		// Too many "headings" means bold is used for emphasis, not structure.
		$candidates = count(
			array_filter(
				$items,
				function ( $i ) {
					return ! empty( $i['sig'] );
				}
			)
		);
		$paras      = max( 1, count( $items ) );
		if ( $candidates > 25 || $candidates > $paras * 0.4 ) {
			$this->warnings[] = 'Bold/caps used too often to be headings — no headings created.';
			return array_map(
				function ( $i ) {
					unset( $i['sig'] );
					return $i;
				},
				$items
			);
		}

		foreach ( $items as $k => $it ) {
			if ( empty( $it['sig'] ) ) {
				continue;
			}
			$rank = array_search( $it['sig'], array_values( array_diff( $sig_order, array( 'keyword' ) ) ), true );
			$lvl  = $has_word_headings ? 3 : ( ( 'keyword' === $it['sig'] || 0 === $rank ) ? 2 : 3 );

			$items[ $k ]['type']  = 'heading';
			$items[ $k ]['level'] = $lvl;
			if ( self::is_caps( $it['plain'] ) ) {
				$items[ $k ]['html'] = htmlspecialchars( rtrim( self::title_case( $it['plain'] ), ' :' ), ENT_QUOTES, 'UTF-8' );
			} else {
				// Headings are bold already — drop the redundant inline formatting.
				$items[ $k ]['html'] = htmlspecialchars( rtrim( $it['plain'], ' :' ), ENT_QUOTES, 'UTF-8' );
			}
		}
		return $items;
	}

	/* ------------------------------------------------------------------ */
	/* Heading clean-up rules (from an audit of 1,557 imported headings)   */
	/* ------------------------------------------------------------------ */

	/**
	 * Second pass over the detected headings:
	 *  1. Title page: before the first real paragraph, lines like "The Bible /
	 *     by / Chris Grattan / The Book of Job – …" are not headings. Names,
	 *     "by", the verse and dates are dropped; the line matching the lesson
	 *     title becomes the lecture title. Only the heading right before the
	 *     first paragraph (e.g. "Introduction") stays a heading.
	 *  2. A lecturer's name ("Carol Martin", "by Carol Martin") is never a heading.
	 *  3. Too long (> 100 characters / 14 words) or starting with a quote → paragraph.
	 *  4. 3+ headings in a row with no text between (an outline, a calendar,
	 *     a list of points) → a list.
	 *  5. Sentence-like (ends with "." / "," / ";") → paragraph, except the
	 *     section keywords ("Let's pray.").
	 *  6. "Slide 3", "Chart", a bare Bible reference → paragraph.
	 *  7. After "Notes" / "References" / "Endnotes", no more headings.
	 *  8. Trailing "." / ":" removed; if only h3s are left they become h2s.
	 *
	 * @param string $title Lecture title (may be set or replaced here).
	 */
	private function refine_headings( array $items, array $ctx, &$title ) {
		$names = array();
		foreach ( array_merge( (array) ( $ctx['known_names'] ?? array() ), array( (string) ( $ctx['lecturer'] ?? '' ) ) ) as $n ) {
			foreach ( preg_split( '/\s+[\x{2013}\x{2014}-]\s+/u', (string) $n ) as $part ) {
				$part = self::norm( preg_replace( '/^(dr|rev|pastor|mr|mrs|ms)\.?\s+/i', '', trim( $part ) ) );
				if ( strlen( $part ) >= 6 ) {
					$names[ $part ] = true;
				}
			}
		}
		$lesson = self::norm( $ctx['verse'] ?? '' );
		$titlen = self::norm( $ctx['lesson_title'] ?? '' );

		$is_name = function ( $t ) use ( $names ) {
			$n = self::norm( preg_replace( '/^(by|lecture by|presented by|written by)\s+|^(dr|rev|pastor|mr|mrs|ms)\.?\s+/i', '', trim( $t ) ) );
			return '' !== $n && isset( $names[ $n ] );
		};
		$is_ref = function ( $t ) {
			return (bool) preg_match( '/^(\d\s*)?[a-z]+\.?\s+[\dl]+[:.]\s?[\dl][\dl:\-\x{2013}\x{2014}, ]*(\([a-z]+\))?$/iu', trim( $t ) );
		};
		$to_p = function ( array $it ) {
			$it['type'] = 'p';
			unset( $it['level'] );
			if ( empty( $it['html'] ) ) {
				$it['html'] = htmlspecialchars( $it['plain'], ENT_QUOTES, 'UTF-8' );
			}
			return $it;
		};
		$len = function ( $t ) {
			return function_exists( 'mb_strlen' ) ? mb_strlen( $t ) : strlen( $t );
		};

		// 1. Title page.
		$zone = count( $items );
		foreach ( $items as $k => $it ) {
			if ( 'p' === $it['type'] && empty( $it['list'] ) && $len( $it['plain'] ) >= 100 ) {
				$zone = $k;
				break;
			}
		}
		$out = array();
		foreach ( $items as $k => $it ) {
			if ( $k < $zone && ( 'heading' === $it['type'] || ( 'p' === $it['type'] && empty( $it['list'] ) && $len( $it['plain'] ) < 100 ) ) ) {
				$t = trim( $it['plain'] );
				$n = self::norm( $t );
				if ( preg_match( '/^(by|lecture by|presented by|written by)$/i', $t ) || $is_name( $t ) || $is_ref( $t )
					|| ( $lesson && ( $n === $lesson ) )
					|| preg_match( '/^(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\.?\s+\d{1,2},?\s+\d{4}$/i', $t ) ) {
					continue; // dropped.
				}
				if ( $titlen && strlen( $titlen ) > 6 && ( false !== strpos( $n, $titlen ) || ( strlen( $n ) > 6 && false !== strpos( $titlen, $n ) ) ) ) {
					$title = trim( preg_replace( '/^["\x{201C}\x{2018}\']+|["\x{201D}\x{2019}\']+$/u', '', $t ) );
					continue; // becomes the lecture title.
				}
				$last_before_text = ( $k === $zone - 1 );
				if ( 'heading' === $it['type'] && ! $last_before_text && ! preg_match( self::KEYWORD_HEADINGS, $t ) ) {
					$it = $to_p( $it );
				}
			}
			$out[] = $it;
		}
		$items = $out;

		// 2, 3, 6, 7.
		$after_notes = false;
		foreach ( $items as $k => $it ) {
			if ( 'heading' !== $it['type'] ) {
				continue;
			}
			$t = trim( $it['plain'] );
			if ( $after_notes || $is_name( $t ) || $len( $t ) > 100 || str_word_count( $t ) > 14
				|| preg_match( '/^["\x{201C}\x{2018}]/u', $t ) || $is_ref( $t )
				|| preg_match( '/^(slide|chart|picture|image|graphic)(\s*#?\d+)?\s*[:.]?$/i', $t ) ) {
				$items[ $k ] = $to_p( $it );
				continue;
			}
			if ( preg_match( '/^(notes|end\s*notes|footnotes|references|sources|bibliography|works cited)\s*:?$/i', $t ) ) {
				$after_notes = true;
			}
		}

		// 4. Runs of headings → list.
		$n = count( $items );
		for ( $k = 0; $k < $n; $k++ ) {
			if ( 'heading' !== $items[ $k ]['type'] ) {
				continue;
			}
			$j      = $k;
			$num_re = '/^\s*(\d+|[ivx]+|[a-z])[.)]\s+/iu';
			while ( $j + 1 < $n && 'heading' === $items[ $j + 1 ]['type']
				&& ( $items[ $j + 1 ]['level'] === $items[ $k ]['level']
					|| ( preg_match( $num_re, $items[ $k ]['plain'] ) && preg_match( $num_re, $items[ $j + 1 ]['plain'] ) ) ) ) {
				$j++;
			}
			$size     = $j - $k + 1;
			$numbered = true;
			for ( $x = $k; $x <= $j; $x++ ) {
				if ( ! preg_match( '/^\s*(\d+|[ivx]+|[a-z])[.)]\s+/iu', $items[ $x ]['plain'] ) ) {
					$numbered = false;
				}
			}
			if ( $size >= 3 || ( $size >= 2 && $numbered ) ) {
				for ( $x = $k; $x <= $j; $x++ ) {
					$it         = $to_p( $items[ $x ] );
					$text       = $numbered ? preg_replace( '/^\s*(\d+|[ivx]+|[a-z])[.)]\s+/iu', '', $it['plain'] ) : $it['plain'];
					$it['html'] = htmlspecialchars( rtrim( $text ), ENT_QUOTES, 'UTF-8' );
					$it['list'] = array(
						'level'   => 0,
						'ordered' => $numbered,
						'fmt'     => $numbered ? 'decimal' : 'bullet',
					);
					$items[ $x ] = $it;
				}
			}
			$k = $j;
		}

		// A lone numbered point ("1) …" with no "2) …" heading) is not a section heading.
		$numbers = array();
		foreach ( $items as $it ) {
			if ( 'heading' === $it['type'] && preg_match( '/^\s*(\d+)[.)]\s+/', $it['plain'], $m ) ) {
				$numbers[ (int) $m[1] ] = true;
			}
		}
		foreach ( $items as $k => $it ) {
			if ( 'heading' === $it['type'] && preg_match( '/^\s*(\d+)[.)]\s+/', $it['plain'], $m )
				&& empty( $numbers[ (int) $m[1] + 1 ] ) && empty( $numbers[ (int) $m[1] - 1 ] ) ) {
				$items[ $k ] = $to_p( $it );
			}
		}

		// 5 + 8.
		$levels = array();
		foreach ( $items as $k => $it ) {
			if ( 'heading' !== $it['type'] ) {
				continue;
			}
			$t = trim( $it['plain'] );
			$fragment = preg_match( '/[,;\x{2013}\x{2014}-]$|\s(and|or|but|the|of|to|a|an|in|with|for|that)$/iu', $t ) // "Fight, and"
				|| preg_match( '/^\p{Ll}/u', $t );                                                                  // starts lower-case
			if ( $fragment || ( preg_match( '/[.,;]$/', $t ) && ! preg_match( self::KEYWORD_HEADINGS, $t ) ) ) {
				$items[ $k ] = $to_p( $it );
				continue;
			}
			$items[ $k ]['html'] = preg_replace( '/[\s.:]+$/u', '', $it['html'] );
			$levels[]            = (int) $it['level'];
		}
		$real_h2 = 0;
		foreach ( $items as $it ) {
			if ( 'heading' === $it['type'] && 2 === (int) $it['level'] && ! preg_match( self::KEYWORD_HEADINGS, trim( $it['plain'] ) ) ) {
				$real_h2++;
			}
		}
		if ( $levels && 0 === $real_h2 ) {
			foreach ( $items as $k => $it ) {
				if ( 'heading' === $it['type'] && 3 === (int) $it['level'] ) {
					$items[ $k ]['level'] = 2;
				}
			}
		}
		return array_values( $items );
	}

	/**
	 * Layer-1 structure rules:
	 *  - Empty headings (nothing under them before the next heading of the same
	 *    or higher level, e.g. "Notes" / "Let's pray" at the very end) are
	 *    removed — a prayer heading with nothing under it stays as plain text.
	 *  - Scripture quotes become quote blocks: a paragraph wrapped in quotation
	 *    marks, a reference followed by a quotation ("Acts 6:1 (NKJV) “Now …”"),
	 *    or a paragraph indented further than the lecture's normal text that
	 *    quotes / cites Scripture. The reference becomes the citation.
	 *  - The text under "Let's Pray" / "Closing Prayer" is boxed (see render()).
	 */
	private function mark_quotes_and_prayers( array $items ) {
		// Empty headings.
		$n   = count( $items );
		$out = array();
		for ( $k = 0; $k < $n; $k++ ) {
			$it = $items[ $k ];
			if ( 'heading' === $it['type'] ) {
				$next = $items[ $k + 1 ] ?? null;
				$empty = null === $next || ( 'heading' === $next['type'] && (int) $next['level'] <= (int) $it['level'] );
				if ( $empty ) {
					if ( preg_match( self::PRAYER_HEADING, trim( $it['plain'] ) ) ) {
						$it['type'] = 'p';
						unset( $it['level'] );
						$it['html'] = htmlspecialchars( trim( $it['plain'] ), ENT_QUOTES, 'UTF-8' );
						$out[]      = $it;
					}
					continue; // drop "Notes", "References", … with nothing under them.
				}
			}
			$out[] = $it;
		}
		$items = $out;

		// Typical indent of body text.
		$indents = array();
		foreach ( $items as $it ) {
			if ( 'p' === $it['type'] && empty( $it['list'] ) ) {
				$indents[] = (int) ( $it['indent'] ?? 0 );
			}
		}
		sort( $indents );
		$median = $indents ? $indents[ (int) floor( count( $indents ) / 2 ) ] : 0;

		$ref = '(?:\d\s*)?[A-Z][a-z]+\.?\s+\d+[:.]\d+(?:[\-\x{2013}\x{2014},]\s*\d+(?::\d+)?)*[a-z]?(?:\s*\((?:NKJV|NIV|ESV|KJV|NASB|NLT|NRSV|AMP|MSG|CSB|HCSB|RSV|NET|TLB|ASV|NAS|NIrV|CEV|GNT|NCV)\))?';
		foreach ( $items as $k => $it ) {
			if ( 'p' !== $it['type'] || ! empty( $it['list'] ) ) {
				continue;
			}
			$t   = trim( $it['plain'] );
			$len = function_exists( 'mb_strlen' ) ? mb_strlen( $t ) : strlen( $t );
			if ( $len < 30 ) {
				continue;
			}
			$cite  = '';
			$quote = false;
			// "Acts 6:1 (NKJV) “Now in those days …”" / "Acts 2:47b (NKJV) says, “…”"
			if ( preg_match( '/^(' . $ref . ')\s*(?:says,?|reads,?|:|\x{2013}|-)?\s*([\x{201C}"].+)$/u', $t, $m ) ) {
				$cite  = $m[1];
				$quote = true;
			} elseif ( preg_match( '/^[\x{201C}"].{20,}[\x{201D}"][.!?]?\s*(?:\(?(' . $ref . ')\)?)?\.?$/u', $t, $m ) ) {
				// Whole paragraph in quotation marks, optionally followed by its reference.
				$cite  = $m[1] ?? '';
				$quote = true;
			} elseif ( (int) ( $it['indent'] ?? 0 ) >= $median + 360 && ( preg_match( '/[\x{201C}"]/u', $t ) || preg_match( '/\(?' . $ref . '\)?\.?$/u', $t ) || ! empty( $it['all_i'] ) ) ) {
				// Indented further than the body (≥ ¼ inch) and quoting / citing Scripture.
				if ( preg_match( '/\(?(' . $ref . ')\)?\.?$/u', $t, $m ) ) {
					$cite = $m[1];
				}
				$quote = true;
			}
			if ( $quote ) {
				$body = $t;
				if ( '' !== $cite ) {
					// Take the reference out of the text — it is shown as the citation.
					$q    = preg_quote( $cite, '/' );
					$body = preg_replace( '/^' . $q . '\s*(?:says,?|reads,?|:|\x{2013}|-)?\s*|\s*[\x{2013}\x{2014}-]?\s*\(?' . $q . '\)?\.?$/u', '', $body );
				}
				$items[ $k ]['quote'] = true;
				$items[ $k ]['cite']  = $cite;
				$items[ $k ]['html']  = '' !== $cite ? htmlspecialchars( trim( $body ), ENT_QUOTES, 'UTF-8' ) : $it['html'];
			}
		}
		return $items;
	}

	private function word_heading_level( array $it ) {
		$s = $it['style'];
		if ( preg_match( '/^heading\s*([1-9])$/', $s, $m ) ) {
			return 1 === (int) $m[1] ? 2 : ( 2 === (int) $m[1] ? 3 : 4 );
		}
		if ( 'title' === $s || 'subtitle' === $s ) {
			return 2;
		}
		if ( null !== $it['outline'] && $it['outline'] < 3 && ! $it['list'] ) {
			return 0 === $it['outline'] ? 2 : 3;
		}
		return 0;
	}

	/** "Introduction", "Application", "Part 2 – …": always a heading when it stands alone. */
	private function keyword_signature( array $it ) {
		$t   = trim( $it['plain'] );
		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $t ) : strlen( $t );
		if ( $len > 100 || str_word_count( $t ) > 14 ) {
			return '';
		}
		$core = preg_replace( '/^([ivx]+|\d+|[a-z])[.)]\s+/iu', '', $t ); // "1. Introduction", "IV. Conclusion"
		if ( preg_match( self::KEYWORD_HEADINGS, $core ) || ( preg_match( self::KEYWORD_PREFIX, $core ) && ! preg_match( '/[.;,]$/', $t ) ) ) {
			// Same style as any other heading the document uses (caps/bold), else its own.
			return self::is_caps( $t ) ? 'caps' : ( $it['all_bold'] ? 'bold' : 'keyword' );
		}
		return '';
	}

	/** A line that is just the lecturer's name ("Carol Martin", "by Carol Martin"). */
	private function is_lecturer_line( $t ) {
		if ( '' === $this->lecturer ) {
			return false;
		}
		$n = self::norm( preg_replace( '/^(by|lecture by|presented by)\s+|^(dr|rev|pastor|mr|mrs|ms)\.?\s+/i', '', trim( $t ) ) );
		return '' !== $n && ( $n === $this->lecturer || ( strlen( $n ) < 40 && false !== strpos( $n, $this->lecturer ) ) );
	}

	/** Returns 'caps' | 'bold' | 'underline' if the line looks like a heading, else ''. */
	private function heuristic_signature( array $it ) {
		$t   = $it['plain'];
		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $t ) : strlen( $t );
		if ( $len < 3 || $len > 100 || str_word_count( $t ) > 14 ) {
			return '';
		}
		if ( preg_match( '/[.,;!?]["\x{201D}\')]?$/u', $t ) && ! preg_match( '/\?$/', $t ) ) {
			return ''; // ends like a sentence.
		}
		if ( preg_match( '/^["\x{201C}]/u', $t ) ) {
			return ''; // a quotation.
		}
		// A bare Bible reference ("John 3:16") is not a heading.
		if ( preg_match( '/^(\d\s*)?[a-z]+\.?\s+\d+:\d+[\d:\-\x{2013}, ]*(\([a-z]+\))?$/iu', $t ) ) {
			return '';
		}
		if ( self::is_caps( $t ) && ( $it['all_bold'] || $it['all_u'] || $len <= 60 ) ) {
			return 'caps';
		}
		if ( $it['all_bold'] ) {
			return $it['list'] ? 'bold-list' : 'bold';
		}
		if ( $it['all_u'] ) {
			return 'underline';
		}
		return '';
	}

	/**
	 * Plain text only (old .doc): a short title-like line that sits between
	 * proper paragraphs ("Introduction", "The Harvest of the Earth").
	 */
	private function plain_signature( array $it, $prev, $next ) {
		$t   = $it['plain'];
		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $t ) : strlen( $t );
		if ( $it['list'] || $len < 3 || $len > 70 || str_word_count( $t ) > 10 ) {
			return '';
		}
		if ( ! preg_match( '/^[\p{Lu}0-9IVX]/u', $t ) || preg_match( '/[.,;:!"\x{201D}]$/u', $t ) ) {
			return '';
		}
		if ( preg_match( '/^(\d\s*)?[a-z]+\.?\s+\d+:\d+/iu', $t ) ) {
			return ''; // Bible reference.
		}
		$long = function ( $x ) {
			return $x && 'p' === $x['type'] && ( function_exists( 'mb_strlen' ) ? mb_strlen( $x['plain'] ) : strlen( $x['plain'] ) ) > 140;
		};
		if ( ! $long( $next ) ) {
			return '';
		}
		// Previous line must not be another short line (that would be a list / outline).
		if ( $prev && 'p' === $prev['type'] && ! $long( $prev ) && empty( $prev['sig'] ) && 'heading' !== ( $prev['type'] ?? '' ) ) {
			$plen = function_exists( 'mb_strlen' ) ? mb_strlen( $prev['plain'] ) : strlen( $prev['plain'] );
			if ( $plen <= 70 ) {
				return '';
			}
		}
		return 'plain';
	}

	/* ------------------------------------------------------------------ */
	/* Output                                                              */
	/* ------------------------------------------------------------------ */

	private function render( array $items ) {
		$out = '';
		$n   = count( $items );
		for ( $k = 0; $k < $n; $k++ ) {
			$it = $items[ $k ];
			if ( 'heading' === $it['type'] ) {
				$lvl     = (int) $it['level'];
				$attr    = 2 === $lvl ? '' : ' {"level":' . $lvl . '}';
				$heading = "<!-- wp:heading{$attr} -->\n<h{$lvl} class=\"wp-block-heading\">{$it['html']}</h{$lvl}>\n<!-- /wp:heading -->\n\n";
				// Prayer: box the heading and everything under it (up to the next heading).
				if ( preg_match( self::PRAYER_HEADING, trim( $it['plain'] ) ) ) {
					$inner = array();
					while ( $k + 1 < $n && 'heading' !== $items[ $k + 1 ]['type'] ) {
						$inner[] = $items[ ++$k ];
					}
					$out .= "<!-- wp:group {\"className\":\"potw-prayer\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group potw-prayer\">" . $heading . $this->render( $inner ) . "\n</div>\n<!-- /wp:group -->\n\n";
					continue;
				}
				$out .= $heading;
				continue;
			}
			if ( ! empty( $it['quote'] ) ) {
				$paras = array();
				$cite  = '';
				// One quote = consecutive quoted paragraphs, ending at the one that carries the reference.
				while ( $k < $n && ! empty( $items[ $k ]['quote'] ) ) {
					$paras[] = $items[ $k ]['html'];
					$cite    = $items[ $k ]['cite'];
					$k++;
					if ( '' !== $cite ) {
						break;
					}
				}
				$k--;
				$out .= $this->render_quote( $paras, $cite );
				continue;
			}
			if ( 'table' === $it['type'] ) {
				$out .= $this->render_table( $it['rows'] );
				continue;
			}
			if ( $it['list'] ) {
				$group = array();
				while ( $k < $n && 'p' === $items[ $k ]['type'] && $items[ $k ]['list'] ) {
					$group[] = $items[ $k ];
					$k++;
				}
				$k--;
				$out .= $this->render_list( $group, 0 ) . "\n\n";
				continue;
			}
			$out .= "<!-- wp:paragraph -->\n<p>{$it['html']}</p>\n<!-- /wp:paragraph -->\n\n";
		}
		return trim( $out );
	}

	/** Scripture quote → core/quote, with the reference as the citation. */
	private function render_quote( array $paras, $cite ) {
		$h = "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\">";
		foreach ( $paras as $p ) {
			$h .= "<!-- wp:paragraph -->\n<p>{$p}</p>\n<!-- /wp:paragraph -->";
		}
		if ( '' !== $cite ) {
			$h .= '<cite>' . htmlspecialchars( $cite, ENT_QUOTES, 'UTF-8' ) . '</cite>';
		}
		return $h . "</blockquote>\n<!-- /wp:quote -->\n\n";
	}

	/** Render consecutive list items (with nesting) as core/list blocks. */
	private function render_list( array $group, $base ) {
		$ordered = ! empty( $group[0]['list']['ordered'] );
		$tag     = $ordered ? 'ol' : 'ul';
		$attr    = $ordered ? ' {"ordered":true}' : '';
		$html    = "<!-- wp:list{$attr} -->\n<{$tag} class=\"wp-block-list\">";

		$count = count( $group );
		for ( $i = 0; $i < $count; $i++ ) {
			$item  = $group[ $i ];
			$html .= "<!-- wp:list-item -->\n<li>" . $item['html'];
			// Children: following items with a deeper level.
			$children = array();
			while ( $i + 1 < $count && $group[ $i + 1 ]['list']['level'] > $item['list']['level'] ) {
				$children[] = $group[ ++$i ];
			}
			if ( $children ) {
				$html .= $this->render_list( $children, $base + 1 );
			}
			$html .= "</li>\n<!-- /wp:list-item -->";
		}
		return $html . "</{$tag}>\n<!-- /wp:list -->";
	}

	private function render_table( array $rows ) {
		$h = "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><tbody>";
		$w = max( array_map( 'count', $rows ) );
		foreach ( $rows as $r ) {
			$h .= '<tr>';
			for ( $c = 0; $c < $w; $c++ ) {
				$h .= '<td>' . ( $r[ $c ] ?? '' ) . '</td>';
			}
			$h .= '</tr>';
		}
		return $h . "</tbody></table></figure>\n<!-- /wp:table -->\n\n";
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	public static function norm( $s ) {
		$s = html_entity_decode( (string) $s, ENT_QUOTES, 'UTF-8' );
		$s = strtolower( preg_replace( '/[^\p{L}\p{N}]+/u', '', $s ) );
		return $s;
	}

	public static function is_caps( $t ) {
		$letters = preg_replace( '/[^\p{L}]/u', '', $t );
		if ( strlen( $letters ) < 4 ) {
			return false;
		}
		$upper = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $letters ) : strtoupper( $letters );
		return $letters === $upper;
	}

	public static function title_case( $t ) {
		$small = array( 'a', 'an', 'and', 'as', 'at', 'but', 'by', 'for', 'in', 'of', 'on', 'or', 'the', 'to', 'vs', 'with', 'from', 'into', 'nor' );
		$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $t ) : strtolower( $t );
		$words = preg_split( '/(\s+)/u', $lower, -1, PREG_SPLIT_DELIM_CAPTURE );
		$first = true;
		$prev  = '';
		foreach ( $words as &$w ) {
			if ( '' === trim( $w ) ) {
				continue;
			}
			$core = preg_replace( '/^[^\p{L}\p{N}]+/u', '', $w );
			if ( $first || ! in_array( $core, $small, true ) || preg_match( '/[:?!.\x{2013}\x{2014}-]$/u', $prev ) ) {
				// Capitalise the first letter (after any leading punctuation), keep roman numerals upper.
				if ( preg_match( '/^([^\p{L}]*)(i{1,3}|iv|v|vi{1,3}|ix|x)([^\p{L}]*)$/u', $w, $m ) ) {
					$w = $m[1] . strtoupper( $m[2] ) . $m[3];
				} else {
					$w = preg_replace_callback(
						'/^([^\p{L}]*)(\p{L})/u',
						function ( $m ) {
							return $m[1] . ( function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $m[2] ) : strtoupper( $m[2] ) );
						},
						$w
					);
				}
			}
			// God-language stays capitalised.
			$w     = preg_replace_callback(
				'/\b(god|lord|jesus|christ|spirit|holy|israel|zion)\b/u',
				function ( $m ) {
					return ucfirst( $m[1] );
				},
				$w
			);
			$first = false;
			$prev  = $w;
		}
		unset( $w );
		return implode( '', $words );
	}
}

if ( ! function_exists( 'potw_li_strip_tags_docx' ) ) {
	function potw_li_strip_tags_docx( $s ) {
		return html_entity_decode( strip_tags( $s ), ENT_QUOTES, 'UTF-8' );
	}
}

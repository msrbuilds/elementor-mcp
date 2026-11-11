<?php
/**
 * Read-only policy enforced over a token stream.
 *
 * Every rule here inspects TYPED TOKENS from EMCP_Tools_SQL_Lexer, never text.
 * That is the difference from the old guard: a keyword inside a string literal
 * is a string token and can no longer be mistaken for a keyword, an identifier
 * is an identifier whatever characters it contains, and a byte the lexer cannot
 * classify aborts the whole statement instead of vanishing from a normalized
 * copy.
 *
 * Table references are gathered by OVER-approximating: every identifier in the
 * statement is treated as a possible table name. That can refuse a column that
 * happens to be called `wp_users`, which is rare and fails closed, and it means
 * no clever aliasing or nesting can smuggle a protected name past the check.
 *
 * @package EMCP_Tools
 * @since   3.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decides whether a statement is a safe read.
 *
 * @since 3.13.0
 */
class EMCP_Tools_SQL_Policy {

	/**
	 * The MySQL session modes that change TOKENIZATION.
	 *
	 * This list is the correctness boundary of the whole design, so it is stated
	 * explicitly rather than left implicit. MySQL has exactly two such modes:
	 *
	 *   ANSI_QUOTES          a double quote delimits an identifier, not a string
	 *   NO_BACKSLASH_ESCAPES a backslash inside a literal is an ordinary byte
	 *
	 * Other modes (PIPES_AS_CONCAT, HIGH_NOT_PRECEDENCE, and the rest) change
	 * how a parsed statement is INTERPRETED, not how it splits into tokens, so
	 * they cannot hide a table name or a keyword from these rules.
	 *
	 * A statement is accepted only if it is safe under EVERY combination, so the
	 * guard never has to know the session's actual mode. If MySQL ever adds a
	 * third tokenization mode, this constant is the one place to extend.
	 *
	 * @var array[] Each entry is array{backslash_escapes:bool,ansi_quotes:bool}.
	 */
	/**
	 * Tokenizer failures that a session mode can legitimately cause.
	 *
	 * A statement whose quoting only balances under one mode is a syntax error
	 * under the other, so that reading is not a statement the server could have
	 * run. Every OTHER failure (unknown byte, bad encoding, executable comment,
	 * unterminated comment) is mode-independent: it means this guard does not
	 * understand the input, which is exactly when it must refuse.
	 *
	 * @var string[]
	 */
	const MODE_DEPENDENT_ERRORS = array( 'unterminated_quote' );

	const MODE_FLAGS = array(
		array( true, false ),
		array( true, true ),
		array( false, false ),
		array( false, true ),
	);

	/** Statements that may begin a read. */
	const READ_KEYWORDS = array( 'select', 'with', 'show', 'describe', 'desc', 'explain', 'table', 'values' );

	/** Keywords that make a statement a write, DDL, or otherwise unsafe. */
	const FORBIDDEN_KEYWORDS = array(
		'insert', 'update', 'delete', 'replace', 'merge', 'drop', 'truncate',
		'alter', 'create', 'rename', 'grant', 'revoke', 'handler', 'call',
		'lock', 'unlock', 'prepare', 'execute', 'deallocate', 'load', 'do',
		'set', 'start', 'commit', 'rollback', 'savepoint', 'begin', 'use',
		'flush', 'reset', 'kill', 'shutdown', 'install', 'uninstall',
		'analyze', 'optimize', 'repair', 'check', 'checksum', 'binlog',
		'purge', 'change', 'stop', 'restart', 'clone', 'import',
	);

	/**
	 * Forbidden keywords that are also read-only string functions (`REPLACE( s, a, b )`,
	 * `INSERT( s, pos, len, new )`). Allowed only when followed by `(`; the write
	 * statements put a table name or INTO there, and the statement must open with a
	 * read keyword anyway (#181).
	 */
	const KEYWORD_FUNCTIONS = array( 'replace', 'insert' );

	/** Keywords that read or write server files. */
	const FILE_KEYWORDS = array( 'outfile', 'dumpfile', 'infile' );

	/** Functions that read server files. */
	const FILE_FUNCTIONS = array( 'load_file', 'lo_import', 'lo_export' );

	/**
	 * Functions that consume server resources or touch the filesystem.
	 *
	 * Matched only when the identifier is immediately followed by `(`, so a
	 * column named `sleep` is fine.
	 */
	const FORBIDDEN_FUNCTIONS = array(
		'sleep', 'benchmark', 'get_lock', 'release_lock', 'release_all_locks',
		'is_free_lock', 'is_used_lock', 'master_pos_wait', 'source_pos_wait',
		'wait_for_executed_gtid_set', 'sys_exec', 'sys_eval',
	);

	/** Schemas that are never a legitimate target of this tool. */
	const SYSTEM_SCHEMAS = array( 'mysql', 'information_schema', 'performance_schema', 'sys' );

	/**
	 * Analyze a statement under one mode reading.
	 *
	 * @since 3.13.0
	 * @param string $sql               Raw SQL.
	 * @param bool   $backslash_escapes Mode flag.
	 * @param bool   $ansi_quotes       Mode flag.
	 * @return array{identifiers:string[],qualifiers:string[],first:string,limit:?int}|WP_Error
	 */
	public static function analyze_one( string $sql, bool $backslash_escapes, bool $ansi_quotes ) {
		$tokens = EMCP_Tools_SQL_Lexer::tokenize( $sql, $backslash_escapes, $ansi_quotes );
		if ( is_wp_error( $tokens ) ) {
			return $tokens;
		}

		$sig = self::significant( $tokens );
		if ( ! $sig ) {
			return new WP_Error( 'empty_sql', __( 'Empty query.', 'emcp-tools' ) );
		}

		// --- one statement only ------------------------------------------------
		$last = count( $sig ) - 1;
		foreach ( $sig as $n => $t ) {
			if ( EMCP_Tools_SQL_Lexer::T_PUNCT === $t['t'] && ';' === $t['v'] && $n !== $last ) {
				return new WP_Error( 'multi_statement', __( 'Multiple SQL statements are not allowed.', 'emcp-tools' ) );
			}
		}
		if ( EMCP_Tools_SQL_Lexer::T_PUNCT === $sig[ $last ]['t'] && ';' === $sig[ $last ]['v'] ) {
			array_pop( $sig );
			$last--;
		}
		if ( $last < 0 ) {
			return new WP_Error( 'empty_sql', __( 'Empty query.', 'emcp-tools' ) );
		}

		// --- the statement must OPEN with a read keyword ------------------------
		// Leading parens are skipped so a parenthesized UNION branch is accepted.
		$k = 0;
		while ( $k <= $last && EMCP_Tools_SQL_Lexer::T_PUNCT === $sig[ $k ]['t'] && '(' === $sig[ $k ]['v'] ) {
			$k++;
		}
		$first = ( $k <= $last && EMCP_Tools_SQL_Lexer::T_IDENT === $sig[ $k ]['t'] && ! $sig[ $k ]['quoted'] )
			? strtolower( $sig[ $k ]['name'] )
			: '';
		if ( ! in_array( $first, self::READ_KEYWORDS, true ) ) {
			return new WP_Error(
				'not_read_only',
				$first
					/* translators: %s: SQL keyword. */
					? sprintf( __( 'Only read-only queries are allowed (got %s).', 'emcp-tools' ), strtoupper( $first ) )
					: __( 'Only read-only queries are allowed.', 'emcp-tools' )
			);
		}

		$identifiers = array();
		$qualifiers  = array();

		foreach ( $sig as $n => $t ) {
			// `:=` assigns a session variable. That is a side effect, so it is not
			// a read however harmless the assigned value looks.
			if ( EMCP_Tools_SQL_Lexer::T_PUNCT === $t['t'] && ':=' === $t['v'] ) {
				return new WP_Error( 'not_read_only', __( 'Variable assignment is not allowed in a read-only query.', 'emcp-tools' ) );
			}
			if ( EMCP_Tools_SQL_Lexer::T_IDENT !== $t['t'] ) {
				continue;
			}
			$lower = strtolower( $t['name'] );

			// A BARE identifier can be a keyword; a quoted one never is.
			if ( ! $t['quoted'] ) {
				$next = self::next_significant( $sig, $n );
				$call = $next && EMCP_Tools_SQL_Lexer::T_PUNCT === $next['t'] && '(' === $next['v'];
				if (
					in_array( $lower, self::FILE_KEYWORDS, true )
					|| ( $call && in_array( $lower, self::FILE_FUNCTIONS, true ) )
					|| 'load' === $lower
				) {
					return new WP_Error( 'file_access_blocked', __( 'File-access SQL (OUTFILE/DUMPFILE/LOAD_FILE/LOAD DATA) is not allowed.', 'emcp-tools' ) );
				}
				if ( in_array( $lower, self::FORBIDDEN_KEYWORDS, true ) && ! ( $call && in_array( $lower, self::KEYWORD_FUNCTIONS, true ) ) ) {
					return new WP_Error( 'not_read_only', __( 'The query contains a write or unsafe keyword.', 'emcp-tools' ) );
				}
				if ( $call && in_array( $lower, self::FORBIDDEN_FUNCTIONS, true ) ) {
					return new WP_Error( 'unsafe_function_blocked', __( 'Delay, lock, and other resource-consuming SQL functions are not allowed.', 'emcp-tools' ) );
				}
			}

			$identifiers[] = $lower;

			// `a . b` makes `a` a qualifier (schema or table).
			$next = self::next_significant( $sig, $n );
			if ( $next && EMCP_Tools_SQL_Lexer::T_PUNCT === $next['t'] && '.' === $next['v'] ) {
				$qualifiers[] = $lower;
			}
		}

		return array(
			'identifiers' => array_values( array_unique( $identifiers ) ),
			'qualifiers'  => array_values( array_unique( $qualifiers ) ),
			'first'       => $first,
			'limit'       => self::top_level_limit( $sig ),
		);
	}

	/**
	 * Analyze under every mode reading, refusing if any reading refuses.
	 *
	 * @since 3.13.0
	 * @param string $sql Raw SQL.
	 * @return array{identifiers:string[],qualifiers:string[],first:string,limit:?int}|WP_Error
	 */
	public static function analyze( string $sql ) {
		$identifiers = array();
		$qualifiers  = array();
		$limits      = array();
		$first       = '';
		$parsed      = 0;
		$first_error = null;

		foreach ( self::MODE_FLAGS as $flags ) {
			$one = self::analyze_one( $sql, $flags[0], $flags[1] );
			if ( is_wp_error( $one ) ) {
				// A statement that does not even tokenize under a given mode is a
				// SYNTAX ERROR in that mode, so it is not the reading the server
				// could have executed, and skipping it is safe. That is only true
				// for failures the mode itself causes; everything else means we do
				// not understand the input and must refuse outright.
				if ( ! in_array( $one->get_error_code(), self::MODE_DEPENDENT_ERRORS, true ) ) {
					return $one;
				}
				if ( null === $first_error ) {
					$first_error = $one;
				}
				continue;
			}
			++$parsed;
			$identifiers = array_merge( $identifiers, $one['identifiers'] );
			$qualifiers  = array_merge( $qualifiers, $one['qualifiers'] );
			$limits[]    = $one['limit'];
			$first       = $one['first'];
		}

		// No reading parsed: the statement is malformed under every mode.
		if ( 0 === $parsed ) {
			return null !== $first_error
				? $first_error
				: new WP_Error( 'sql_unparsable', __( 'The query could not be interpreted.', 'emcp-tools' ) );
		}

		return array(
			'identifiers' => array_values( array_unique( $identifiers ) ),
			'qualifiers'  => array_values( array_unique( $qualifiers ) ),
			'first'       => $first,
			// A bound only counts if EVERY reading agrees there is one; otherwise
			// the caller must add its own.
			'limit'       => in_array( null, $limits, true ) ? null : max( $limits ),
		);
	}

	/**
	 * Full read-only verdict, including protected and system tables.
	 *
	 * @since 3.13.0
	 * @param string   $sql       Raw SQL.
	 * @param string[] $protected Protected table names.
	 * @return true|WP_Error
	 */
	public static function check( string $sql, array $protected = array() ) {
		$a = self::analyze( $sql );
		if ( is_wp_error( $a ) ) {
			return $a;
		}
		foreach ( $a['qualifiers'] as $q ) {
			if ( in_array( $q, self::SYSTEM_SCHEMAS, true ) ) {
				return new WP_Error(
					'protected_read',
					__( 'Reading the server system schemas (mysql, information_schema, performance_schema, sys) is not allowed.', 'emcp-tools' )
				);
			}
		}
		foreach ( $protected as $p ) {
			$p = strtolower( trim( (string) $p ) );
			if ( '' !== $p && in_array( $p, $a['identifiers'], true ) ) {
				return new WP_Error(
					'protected_read',
					__( 'Reading the user tables (passwords, session tokens, activation keys) via raw SQL is not allowed. Use the list-users / get-user tools instead.', 'emcp-tools' )
				);
			}
		}
		return true;
	}

	/**
	 * Does the statement name any of $tables?
	 *
	 * @since 3.13.0
	 * @param string   $sql    Raw SQL.
	 * @param string[] $tables Names.
	 * @return bool True also when the statement cannot be parsed (fail closed).
	 */
	public static function touches_tables( string $sql, array $tables ): bool {
		$a = self::analyze( $sql );
		if ( is_wp_error( $a ) ) {
			// A statement with no tokens references nothing. Every other failure
			// means we could not read it, and then we must assume the worst.
			return 'empty_sql' !== $a->get_error_code();
		}
		foreach ( $tables as $t ) {
			$t = strtolower( trim( (string) $t ) );
			if ( '' !== $t && in_array( $t, $a['identifiers'], true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Does the statement reference a server system schema?
	 *
	 * @since 3.13.0
	 * @param string $sql Raw SQL.
	 * @return bool True also when the statement cannot be parsed (fail closed).
	 */
	public static function references_system_schema( string $sql ): bool {
		$a = self::analyze( $sql );
		if ( is_wp_error( $a ) ) {
			return 'empty_sql' !== $a->get_error_code();
		}
		foreach ( $a['qualifiers'] as $q ) {
			if ( in_array( $q, self::SYSTEM_SCHEMAS, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Row count of a TOP-LEVEL trailing LIMIT, or null.
	 *
	 * Depth-aware, so a LIMIT inside a subquery (which bounds nothing at the
	 * outer level) is correctly ignored.
	 *
	 * @param array[] $sig Significant tokens, no trailing semicolon.
	 * @return int|null
	 */
	private static function top_level_limit( array $sig ): ?int {
		$depth  = 0;
		$at     = -1;
		$counts = array();
		foreach ( $sig as $n => $t ) {
			if ( EMCP_Tools_SQL_Lexer::T_PUNCT === $t['t'] ) {
				if ( '(' === $t['v'] ) {
					$depth++;
				} elseif ( ')' === $t['v'] ) {
					$depth--;
				}
				continue;
			}
			if (
				0 === $depth
				&& EMCP_Tools_SQL_Lexer::T_IDENT === $t['t'] && ! $t['quoted']
				&& 'limit' === strtolower( $t['name'] )
			) {
				$at = $n;
			}
		}
		if ( -1 === $at ) {
			return null;
		}
		// Collect the numbers that follow, and require the clause to END the
		// statement: anything after it means this is not the outer bound.
		$rest = array_slice( $sig, $at + 1 );
		foreach ( $rest as $t ) {
			if ( EMCP_Tools_SQL_Lexer::T_NUMBER === $t['t'] ) {
				$counts[] = (int) $t['v'];
				continue;
			}
			if ( EMCP_Tools_SQL_Lexer::T_PUNCT === $t['t'] && ',' === $t['v'] ) {
				continue;
			}
			if (
				EMCP_Tools_SQL_Lexer::T_IDENT === $t['t'] && ! $t['quoted']
				&& 'offset' === strtolower( $t['name'] )
			) {
				continue;
			}
			return null; // Something else trails the LIMIT: not a plain outer bound.
		}
		if ( ! $counts ) {
			return null;
		}
		// `LIMIT n`, `LIMIT skip, count` and `LIMIT count OFFSET skip` all put the
		// ROW COUNT in a different slot; the largest is the safe reading.
		return max( $counts );
	}

	/**
	 * Tokens that carry meaning.
	 *
	 * @param array[] $tokens All tokens.
	 * @return array[]
	 */
	private static function significant( array $tokens ): array {
		$out = array();
		foreach ( $tokens as $t ) {
			if ( EMCP_Tools_SQL_Lexer::T_WHITESPACE === $t['t'] || EMCP_Tools_SQL_Lexer::T_COMMENT === $t['t'] ) {
				continue;
			}
			$out[] = $t;
		}
		return $out;
	}

	/**
	 * @param array[] $sig Significant tokens.
	 * @param int     $n   Current index.
	 * @return array|null
	 */
	private static function next_significant( array $sig, int $n ): ?array {
		return isset( $sig[ $n + 1 ] ) ? $sig[ $n + 1 ] : null;
	}
}

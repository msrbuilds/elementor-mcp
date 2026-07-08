<?php
/**
 * Gutenberg block-tree operations for the block MCP tools.
 *
 * Pure, stateless transforms over WordPress core parse_blocks()/serialize_blocks().
 * Blocks are addressed by an index PATH (array of ints): [0] = first top-level
 * block (after stripping whitespace separators), [2,1] = innerBlocks[1] of
 * top-level block 2. All mutation methods return a NEW tree; none mutate in place.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.1.0
 */
class EMCP_Tools_Block_Tree {

	/**
	 * Parse post content into a clean top-level block tree.
	 *
	 * @param string $content Post content (block markup).
	 * @return array<int,array> Block-array tree.
	 */
	public static function from_markup( string $content ): array {
		return self::strip_separators( parse_blocks( $content ) );
	}

	/**
	 * Serialize a top-level block tree back to markup (blocks joined by blank lines).
	 *
	 * @param array $blocks Block-array tree.
	 * @return string Block markup.
	 */
	public static function to_markup( array $blocks ): string {
		return implode( "\n\n", array_map( 'serialize_block', array_values( $blocks ) ) );
	}

	/**
	 * Drop top-level whitespace-only separator blocks (blockName null + blank HTML).
	 *
	 * @param array $blocks Parsed top-level blocks.
	 * @return array<int,array>
	 */
	public static function strip_separators( array $blocks ): array {
		$out = array();
		foreach ( $blocks as $b ) {
			$is_sep = ( null === ( $b['blockName'] ?? null ) ) && ( '' === trim( (string) ( $b['innerHTML'] ?? '' ) ) );
			if ( ! $is_sep ) {
				$out[] = $b;
			}
		}
		return array_values( $out );
	}

	/**
	 * Resolve an index path to a node, or null if out of range.
	 *
	 * @param array $blocks Tree.
	 * @param int[] $path   Index path.
	 * @return array|null
	 */
	public static function at( array $blocks, array $path ): ?array {
		$node = null;
		$list = array_values( $blocks );
		foreach ( $path as $i ) {
			$i = (int) $i;
			if ( ! array_key_exists( $i, $list ) ) {
				return null;
			}
			$node = $list[ $i ];
			$list = array_values( $node['innerBlocks'] ?? array() );
		}
		return $node;
	}

	/**
	 * Insert one or more blocks at a position.
	 *
	 * @param array  $blocks    Tree.
	 * @param array  $newBlocks Parsed blocks to insert.
	 * @param array  $position  { mode: append|prepend|before|after|inside, path?: int[] }.
	 * @return array New tree.
	 */
	public static function insert( array $blocks, array $newBlocks, array $position ): array {
		$mode = $position['mode'] ?? 'append';
		$path = $position['path'] ?? array();
		$new  = array_values( $newBlocks );

		if ( 'append' === $mode ) {
			return array_merge( array_values( $blocks ), $new );
		}
		if ( 'prepend' === $mode ) {
			return array_merge( $new, array_values( $blocks ) );
		}
		if ( 'inside' === $mode ) {
			return self::edit_node(
				$blocks,
				$path,
				static function ( array $node ) use ( $new ) {
					$node['innerBlocks']  = array_merge( array_values( $node['innerBlocks'] ?? array() ), $new );
					$node['innerContent'] = self::inner_content_for( $node['innerBlocks'], $node['innerContent'] ?? array() );
					return $node;
				}
			);
		}
		// before / after: splice into the sibling list at the target index.
		$delta = ( 'after' === $mode ) ? 1 : 0;
		return self::edit_siblings(
			$blocks,
			$path,
			static function ( array $siblings, int $index ) use ( $new, $delta ) {
				array_splice( $siblings, $index + $delta, 0, $new );
				return $siblings;
			}
		);
	}

	/**
	 * Replace the node at a path with one or more blocks.
	 *
	 * @param array $blocks    Tree.
	 * @param int[] $path      Index path.
	 * @param array $newBlocks Replacement blocks.
	 * @return array New tree.
	 */
	public static function replace( array $blocks, array $path, array $newBlocks ): array {
		$new = array_values( $newBlocks );
		return self::edit_siblings(
			$blocks,
			$path,
			static function ( array $siblings, int $index ) use ( $new ) {
				array_splice( $siblings, $index, 1, $new );
				return $siblings;
			}
		);
	}

	/**
	 * Remove the node at a path.
	 *
	 * @param array $blocks Tree.
	 * @param int[] $path   Index path.
	 * @return array New tree.
	 */
	public static function remove( array $blocks, array $path ): array {
		return self::edit_siblings(
			$blocks,
			$path,
			static function ( array $siblings, int $index ) {
				array_splice( $siblings, $index, 1 );
				return $siblings;
			}
		);
	}

	/**
	 * Duplicate the node at a path; insert the copy immediately after it.
	 *
	 * @param array $blocks Tree.
	 * @param int[] $path   Index path.
	 * @return array New tree.
	 */
	public static function duplicate( array $blocks, array $path ): array {
		$node = self::at( $blocks, $path );
		if ( null === $node ) {
			return $blocks;
		}
		return self::insert( $blocks, array( $node ), array( 'mode' => 'after', 'path' => $path ) );
	}

	/**
	 * Move the node at $from to a target position.
	 *
	 * @param array $blocks   Tree.
	 * @param int[] $from     Source path.
	 * @param array $position { mode, path? } target.
	 * @return array New tree.
	 */
	public static function move( array $blocks, array $from, array $position ): array {
		$node = self::at( $blocks, $from );
		if ( null === $node ) {
			return $blocks;
		}
		$mode = $position['mode'] ?? 'append';
		$to   = $position['path'] ?? array();

		// Moving a node relative to itself is a no-op.
		if ( in_array( $mode, array( 'before', 'after' ), true ) && $from === $to ) {
			return $blocks;
		}

		// A move whose target lies inside the moved node's own subtree would remove the
		// node and then fail to re-insert it (its path no longer resolves), losing the
		// block. Reject it as a no-op.
		$depth = count( $from );
		if ( count( $to ) >= $depth && array_slice( $to, 0, $depth ) === $from ) {
			return $blocks;
		}

		// remove() shifts every later sibling under $from's parent left by one. When the
		// target path passes through that parent at a position after $from, the index there
		// is now stale — decrement it so insert() still lands correctly. Applies to every
		// mode (before/after siblings AND inside a later container) at any depth.
		$shift = $depth - 1;
		if ( count( $to ) > $shift
			&& array_slice( $from, 0, $shift ) === array_slice( $to, 0, $shift )
			&& (int) $to[ $shift ] > (int) $from[ $shift ] ) {
			$to[ $shift ]     = (int) $to[ $shift ] - 1;
			$position['path'] = $to;
		}

		$without = self::remove( $blocks, $from );
		return self::insert( $without, array( $node ), $position );
	}

	/**
	 * Compact, path-tagged view of the tree for get-post-blocks.
	 *
	 * @param array    $blocks Tree.
	 * @param int|null $depth  Max depth (null = unlimited).
	 * @param int[]    $prefix Internal: current path prefix.
	 * @return array
	 */
	public static function summarize( array $blocks, ?int $depth = null, array $prefix = array() ): array {
		$out = array();
		foreach ( array_values( $blocks ) as $i => $b ) {
			$path  = array_merge( $prefix, array( $i ) );
			$inner = array_values( $b['innerBlocks'] ?? array() );
			$row   = array(
				'path'             => $path,
				'blockName'        => $b['blockName'] ?? null,
				'attributes'       => is_array( $b['attrs'] ?? null ) ? $b['attrs'] : array(),
				'innerBlocksCount' => count( $inner ),
			);
			if ( $inner && ( null === $depth || count( $path ) < $depth ) ) {
				$row['innerBlocks'] = self::summarize( $inner, $depth, $path );
			}
			$out[] = $row;
		}
		return $out;
	}

	// ------------------------------------------------------------------
	// Internals
	// ------------------------------------------------------------------

	/**
	 * Apply $fn to the sibling array that CONTAINS the node at $path.
	 * $fn( array $siblings, int $index ): array returns the new sibling array.
	 */
	private static function edit_siblings( array $blocks, array $path, callable $fn ): array {
		$blocks = array_values( $blocks );
		if ( empty( $path ) ) {
			return $blocks;
		}
		$head = (int) $path[0];
		if ( 1 === count( $path ) ) {
			if ( ! array_key_exists( $head, $blocks ) ) {
				return $blocks;
			}
			return array_values( $fn( $blocks, $head ) );
		}
		if ( ! array_key_exists( $head, $blocks ) ) {
			return $blocks;
		}
		$child                 = $blocks[ $head ];
		$child['innerBlocks']  = self::edit_siblings( array_values( $child['innerBlocks'] ?? array() ), array_slice( $path, 1 ), $fn );
		$child['innerContent'] = self::inner_content_for( $child['innerBlocks'], $child['innerContent'] ?? array() );
		$blocks[ $head ]       = $child;
		return $blocks;
	}

	/**
	 * Apply $fn to the NODE at $path. $fn( array $node ): array returns the new node.
	 */
	private static function edit_node( array $blocks, array $path, callable $fn ): array {
		return self::edit_siblings(
			$blocks,
			$path,
			static function ( array $siblings, int $index ) use ( $fn ) {
				$siblings[ $index ] = $fn( $siblings[ $index ] );
				return $siblings;
			}
		);
	}

	/**
	 * Rebuild a block's innerContent for a changed innerBlocks list, PRESERVING the
	 * container's wrapper markup. innerContent interleaves literal string chunks
	 * (the wrapper HTML) with null placeholders (one per inner block, consumed in
	 * order by serialize_block). We keep the leading chunk (before the first child)
	 * and trailing chunk (after the last child) and re-emit one null per new child.
	 * For an empty container (no existing placeholders) we peel the trailing run of
	 * closing tags so inserted children land INSIDE the wrapper.
	 *
	 * @param array $innerBlocks  New inner blocks.
	 * @param array $prevContent  The block's existing innerContent (pre-change).
	 * @return array
	 */
	private static function inner_content_for( array $innerBlocks, array $prevContent = array() ): array {
		$count = count( array_values( $innerBlocks ) );

		$lead  = '';
		$trail = '';
		$nulls = array();
		foreach ( $prevContent as $i => $chunk ) {
			if ( null === $chunk ) {
				$nulls[] = $i;
			}
		}
		if ( $nulls ) {
			$first = $nulls[0];
			$last  = end( $nulls );
			for ( $i = 0; $i < $first; $i++ ) {
				if ( isset( $prevContent[ $i ] ) && is_string( $prevContent[ $i ] ) ) {
					$lead .= $prevContent[ $i ];
				}
			}
			for ( $i = $last + 1, $n = count( $prevContent ); $i < $n; $i++ ) {
				if ( isset( $prevContent[ $i ] ) && is_string( $prevContent[ $i ] ) ) {
					$trail .= $prevContent[ $i ];
				}
			}
		} else {
			// Empty container (no child placeholders): peel the trailing closing-tag run.
			$joined = '';
			foreach ( $prevContent as $chunk ) {
				if ( is_string( $chunk ) ) {
					$joined .= $chunk;
				}
			}
			if ( '' !== $joined && preg_match( '/((?:\s*<\/[a-zA-Z][a-zA-Z0-9]*>)+\s*)$/', $joined, $m ) ) {
				$trail = $m[1];
				$lead  = substr( $joined, 0, strlen( $joined ) - strlen( $m[1] ) );
			} else {
				$lead = $joined;
			}
		}

		if ( 0 === $count ) {
			$joined = $lead . $trail;
			return '' === $joined ? array() : array( $joined );
		}

		$content = array();
		if ( '' !== $lead ) {
			$content[] = $lead;
		}
		for ( $j = 0; $j < $count; $j++ ) {
			$content[] = null;
			if ( $j < $count - 1 ) {
				$content[] = "\n";
			}
		}
		if ( '' !== $trail ) {
			$content[] = $trail;
		}
		return $content;
	}
}

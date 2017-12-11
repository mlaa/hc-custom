<?php
/**
 * Customizations to buddypress-group-email-subscription.
 *
 * @package Hc_Custom
 */

function hcommons_filter_ass_digest_format_item_group( $group_message, $group_id, $type, $activity_ids, $user_id ) {
	global $bp, $ass_email_css;

	$group = groups_get_group( $group_id );

	$group_permalink = bp_get_group_permalink( $group );
	$group_name_link = '<a class="item-group-group-link" href="' . esc_url( $group_permalink ) . '" name="' . esc_attr( $group->slug ) . '">' . esc_html( $group->name ) . '</a>';

	$userdomain = ass_digest_get_user_domain( $user_id );
	$unsubscribe_link = "$userdomain?bpass-action=unsubscribe&group=$group_id&access_key=" . md5( "{$group_id}{$user_id}unsubscribe" . wp_salt() );
	$gnotifications_link = ass_get_login_redirect_url( $group_permalink . 'notifications/' );

	// add the group title bar
	if ( 'dig' === $type ) {
		$group_message = "\n---\n\n<div class=\"item-group-title\" {$ass_email_css['group_title']}>" . sprintf( __( 'Group: %s', 'bp-ass' ), $group_name_link ) . "</div>\n\n";
	} elseif ( 'sum' === $type ) {
		$group_message = "\n---\n\n<div class=\"item-group-title\" {$ass_email_css['group_title']}>" . sprintf( __( 'Group: %s weekly summary', 'bp-ass' ), $group_name_link ) . "</div>\n";
	}

	// add change email settings link
	$group_message .= "\n<div class=\"item-group-settings-link\" {$ass_email_css['change_email']}>";
	$group_message .= __( 'To disable these notifications for this group click ', 'bp-ass' ) . " <a href=\"$unsubscribe_link\">" . __( 'unsubscribe', 'bp-ass' ) . '</a> - ';
	$group_message .= __( 'change ', 'bp-ass' ) . '<a href="' . $gnotifications_link . '">' . __( 'email options', 'bp-ass' ) . '</a>';
	$group_message .= "</div>\n\n";

	$group_message = apply_filters( 'ass_digest_group_message_title', $group_message, $group_id, $type );

	// Sort activity items and group by forum topic, where possible.
	$grouped_activity_ids = array(
		'topics' => array(),
		'other' => array(),
	);

	$topic_activity_map = array();

	foreach ( $activity_ids as $activity_id ) {
		$activity_item = ! empty( $bp->ass->items[ $activity_id ] ) ? $bp->ass->items[ $activity_id ] : false;

		switch ( $activity_item->type ) {
			case 'bbp_topic_create' :
				$topic_id = $activity_item->secondary_item_id;
				$grouped_activity_ids['topics'][] = $topic_id;

				if ( ! isset( $topic_activity_map[ $topic_id ] ) ) {
					$topic_activity_map[ $topic_id ] = array();
				}

				$topic_activity_map[ $topic_id ][] = $activity_id;
			break;

			case 'bbp_reply_create' :
				// Topic may or may not be in this digest queue.
				$topic_id = bbp_get_reply_topic_id( $activity_item->secondary_item_id );
				$grouped_activity_ids['topics'][] = $topic_id;

				if ( ! isset( $topic_activity_map[ $topic_id ] ) ) {
					$topic_activity_map[ $topic_id ] = array();
				}

				$topic_activity_map[ $topic_id ][] = $activity_id;
			break;

			default :
				$grouped_activity_ids['other'][] = $activity_id;
			break;
		}

		$grouped_activity_ids['topics'] = array_unique( $grouped_activity_ids['topics'] );
	}

	// Assemble forum topic markup first.
	foreach ( $grouped_activity_ids['topics'] as $topic_id ) {
		$topic = bbp_get_topic( $topic_id );
		if ( ! $topic ) {
			continue;
		}

		// 'Topic' header.
		$item_message  = '';
		$item_message .= "<div class=\"digest-item\" {$ass_email_css['item_div']}>";

		$item_message .= '<div class="digest-topic-header">';
		$item_message .= sprintf(
			__( 'Topic: %s', 'bp-ass' ),
			sprintf( '<a href="%s">%s</a>', esc_url( get_permalink( $topic_id ) ), esc_html( $topic->post_title ) )
		);
		$item_message .= '</div>'; // .digest-topic-header

		$item_message .= '<div class="digest-topic-items">';
		foreach ( $topic_activity_map[ $topic_id ] as $activity_id ) {
			$activity_item = new BP_Activity_Activity( $activity_id );

			$poster_name = bp_core_get_user_displayname( $activity_item->user_id );
			$poster_url = bp_core_get_user_domain( $activity_item->user_id );
			$topic_name = $topic->post_title;
			$topic_permalink = get_permalink( $topic_id );

			if ( 'bbp_topic_create' === $activity_item->type ) {
				$action_format = '<a href="%s">%s</a> posted on <a href="%s">%s</a>';
			} else {
				$action_format = '<a href="%s">%s</a> started <a href="%s">%s</a>';
			}

			$action = sprintf( $action_format, esc_url( $poster_url ), esc_html( $poster_name ), esc_url( $topic_permalink ), esc_html( $topic_name ) );

			/* Because BuddyPress core set gmt = true, timezone must be added */
			$timestamp = strtotime( $activity_item->date_recorded ) + date( 'Z' );

			$time_posted = date( get_option( 'time_format' ), $timestamp );
			$date_posted = date( get_option( 'date_format' ), $timestamp );

			$item_message .= '<div class="digest-topic-item" style="border-top:1px solid #eee; margin: 15px 0 15px 30px;">';
			$item_message .= "<span class=\"digest-item-action\" {$ass_email_css['item_action']}>" . $action . ": ";
			$item_message .= "<span class=\"digest-item-timestamp\" {$ass_email_css['item_date']}>" . sprintf( __('at %s, %s', 'bp-ass'), $time_posted, $date_posted ) ."</span>";
			$item_message .= "<br><span class=\"digest-item-content\" {$ass_email_css['item_content']}>" . apply_filters( 'ass_digest_content', $activity_item->content, $activity_item, $type ) . "</span>";
			$item_message .=  "</span>\n";
			$item_message .= '</div>'; // .digest-topic-item
		}
		$item_message .= '</div>'; // .digest-topic-items

		$item_message .= '</div>'; // .digest-item

		$group_message .= $item_message;
	}

	// Non-forum-related markup goes at the end.
	foreach ( $grouped_activity_ids['other'] as $activity_id ) {
		// Cache is set earlier in ass_digest_fire()
		$activity_item = ! empty( $bp->ass->items[ $activity_id ] ) ? $bp->ass->items[ $activity_id ] : false;

		if ( ! empty( $activity_item ) ) {
			$group_message .= ass_digest_format_item( $activity_item, $type );
		}
	}

	return $group_message;
};
add_filter( 'ass_digest_format_item_group', 'hcommons_filter_ass_digest_format_item_group', 10, 5 );


/**
 * Remove activity items that don't belong to the current network from digest emails
 */
function hcommons_filter_ass_digest_group_activity_ids( $group_activity_ids ) {
	$network_activity_ids = [];

	foreach ( $group_activity_ids as $group_id => $activity_ids ) {
		if ( Humanities_Commons::$society_id === bp_groups_get_group_type( $group_id ) ) {
			$network_activity_ids[ $group_id ] = $activity_ids;
		}
	}

	return $network_activity_ids;
}
add_action( 'ass_digest_group_activity_ids', 'hcommons_filter_ass_digest_group_activity_ids' );

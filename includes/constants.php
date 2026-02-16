<?php
// constants.php

//========================================================================================================

// Dutch slug for taxonomy
$slug              = 'community';
$slugtype          = 'community-type';
$slugtopics        = 'onderwerpen-community';
$slugaudiences     = 'doelgroepen-community';
$slugoverheidslaag = 'overheidslaag';

if ( get_bloginfo( 'language' ) !== 'nl-NL' ) {
	// non Dutch slugs
	$slug          = 'community';
	$slugtopics    = 'topics-community';
	$slugaudiences = 'audience-community';
}


define( 'DO_COMMUNITY_CPT', $slug );
define( 'DO_COMMUNITYTYPE_CT', $slugtype );
define( 'DO_COMMUNITYTOPICS_CT', $slugtopics );
define( 'DO_COMMUNITYAUDIENCE_CT', $slugaudiences );
define( 'DO_COMMUNITYBESTUURSLAAG_CT', $slugoverheidslaag );

defined( 'DO_COMMUNITY_OVERVIEW_TEMPLATE' ) or define( 'DO_COMMUNITY_OVERVIEW_TEMPLATE', 'template-overview-communities.php' );
defined( 'DO_COMMUNITY_PAGE_RSS_AGENDA' ) or define( 'DO_COMMUNITY_PAGE_RSS_AGENDA', 'template-rss-agenda.php' );
defined( 'DO_COMMUNITY_PAGE_RSS_POSTS' ) or define( 'DO_COMMUNITY_PAGE_RSS_POSTS', 'template-rss-posts.php' );
defined( 'DO_COMMUNITY_DETAIL_TEMPLATE' ) or define( 'DO_COMMUNITY_DETAIL_TEMPLATE', 'template-community-detail.php' );

define( 'DO_COMMUNITYTYPE_CT_VAR', 'communitytype' );
define( 'DO_COMMUNITYTOPICS_CT_VAR', 'communitytopic' );
define( 'DO_COMMUNITYAUDIENCE_CT_VAR', 'communityaudience' );
define( 'DO_COMMUNITYBESTUURSLAAG_CT_VAR', 'communitygov' );
define( 'DO_COMMUNITY_MAX_VAR', 'communitymaxnr' );
define( 'DO_COMMUNITY_MAX_DEFAULT', 20 );
define( 'DO_COMMUNITY_MAX_OPTIONS', array( 10, 20, 50, 100 ) );

define( 'DO_COMMUNITY_RSS_POST_ITEM', 'rsspostitem' );
define( 'DO_COMMUNITY_RSS_EVENT_ITEM', 'rsseventitem' );

define( 'DO_COMMUNITY_RSS_FEED_ITEM', 'wprss_feed_item' );


define( 'COMMUNITY_RSS_ITEM', 'communityrssitem' );

//========================================================================================================

const DO_COMMUNITY_RSS_CRON_MAGIC             = 'rssrtvr_cron_magic';
const DO_COMMUNITY_RSS_MAX_CURL_REDIRECTS     = 20;
const DO_COMMUNITY_RSS_POST_LIFE_CHECK_PERIOD = 3600;
const DO_COMMUNITY_RSS_MIN_UPDATE_TIME        = 1;
const DO_COMMUNITY_RSS_CURL_USER_AGENT        = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36';
const DO_COMMUNITY_RSS_CHECK_DATE             = 'rssrtvr_checkdate';
const DO_COMMUNITY_RSS_POST_LIFE_CHECK_DATE   = 'rssrtvr_post_life_check_date';
const DO_COMMUNITY_RSS_FEED_OPTIONS           = 'rssrtvr_feed_options';
const DO_COMMUNITY_RSS_FEEDS_UPDATED          = 'rssrtvr_feeds_updated';
const DO_COMMUNITY_RSS_SYNDICATED_FEEDS       = 'rssrtvr_syndicated_feeds';
const DO_COMMUNITY_RSS_PULL_MODE              = 'rssrtvr_rss_pull_mode';
const DO_COMMUNITY_RSS_PC_INTERVAL            = 'rssrtvr_pseudo_cron_interval';
const DO_COMMUNITY_RSS_FEED_PULL_TIME         = 'rssrtvr_feed_pull_time';
const DO_COMMUNITY_RSS_MAX_EXEC_TIME          = 'rssrtvr_max_exec_time';
const DO_COMMUNITY_RSS_LOG                    = 'rssrtvr_parse_feed_log';
const DO_COMMUNITY_RSS_KEEP_IMAGES            = 'rssrtvr_keep_images';
const DO_COMMUNITY_RSS_PC_NAME                = 'DO_COMMUNITY_RSS_Custom_interval';
const DO_COMMUNITY_RSS_BLOCK_DIVIDER          = '825670622173';

//========================================================================================================


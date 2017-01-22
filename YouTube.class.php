<?php
/**
 * Parser hook-based extension to show audio and video players
 * from YouTube and other similar sites.
 *
 * @file
 * @ingroup Extensions
 * @author Przemek Piotrowski <ppiotr@wikia-inc.com> for Wikia, Inc.
 * @copyright © 2006-2008, Wikia Inc.
 * @licence GNU General Public Licence 2.0 or later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301
 * USA
 *
 * @todo one class (family) to rule 'em all
 * @todo make width/height_max != width/height_default; aoaudio height may be large - long playlist
 * @todo smart <video> and <audio> tag
 */

class YouTube {

	/**
	 * Register all the new tags with the Parser.
	 */
	public static function registerTags( &$parser ) {
		$parser->setHook( 'youtube', array( __CLASS__, 'embedYouTube' ) );
		$parser->setHook( 'gvideo', array( __CLASS__, 'embedGoogleVideo' ) );
		$parser->setHook( 'aovideo', array( __CLASS__, 'embedArchiveOrgVideo' ) );
		$parser->setHook( 'aoaudio', array( __CLASS__, 'embedArchiveOrgAudio' ) );
		$parser->setHook( 'wegame', array( __CLASS__, 'embedWeGame' ) );
		$parser->setHook( 'tangler', array( __CLASS__, 'embedTangler' ) );
		$parser->setHook( 'gtrailer', array( __CLASS__, 'embedGametrailers' ) );
		$parser->setHook( 'nicovideo', array( __CLASS__, 'embedNicovideo' ) );
		$parser->setHook( 'ggtube', array( __CLASS__, 'embedGoGreenTube' ) );
		return true;
	}

	/**
	 * Get the YouTube video ID from the supplied URL.
	 *
	 * @param string $url YouTube video URL
	 * @return string|bool Video ID on success, boolean false on failure
	 */
	public static function url2ytid( $url ) {
		// @see http://linuxpanda.wordpress.com/2013/07/24/ultimate-best-regex-pattern-to-get-grab-parse-youtube-video-id-from-any-youtube-link-url/
		$pattern = '~(?:http|https|)(?::\/\/|)(?:www.|)(?:youtu\.be\/|youtube\.com(?:\/embed\/|\/v\/|\/watch\?v=|\/ytscreeningroom\?v=|\/feeds\/api\/videos\/|\/user\S*[^\w\-\s]|\S*[^\w\-\s]))([\w\-]{11})[a-z0-9;:@?&%=+\/\$_.-]*~i';
		$id = false;

		if ( preg_match( $pattern, $url, $preg ) ) {
			$id = $preg[1];
		} elseif ( preg_match( '/([0-9A-Za-z_-]+)/', $url, $preg ) ) {
			$id = $preg[1];
		}

		return $id;
	}

	public static function embedYouTube( $input, $argv, $parser ) {
		$ytid   = '';
		$width  = $width_max  = 425;
		$height = $height_max = 355;

		if ( !empty( $argv['ytid'] ) ) {
			$ytid = self::url2ytid( $argv['ytid'] );
		} elseif ( !empty( $input ) ) {
			$ytid = self::url2ytid( $input );
		}

		// Did we not get an ID at all? That can happen if someone enters outright
		// gibberish and/or something that's not a YouTube URL.
		// Let's not even bother with generating useless HTML.
		if ( $ytid === false ) {
			return '';
		}

		// Support the pixel unit (px) in height/width parameters, because apparently
		// a lot of people use it there.
		// This way these parameters won't fail the filter_var() tests below if the
		// user-supplied values were like 450px or 200px or something instead of
		// 450 or 200
		if ( !empty( $argv['height'] ) ) {
			$argv['height'] = str_replace( 'px', '', $argv['height'] );
		}

		if ( !empty( $argv['width'] ) ) {
			$argv['width'] = str_replace( 'px', '', $argv['width'] );
		}

		// Which technology to use for embedding -- HTML5 or Flash Player?
		if ( !empty( $argv['type'] ) && strtolower( $argv['type'] ) == 'flash' ) {
			$width = $width_max = 425;
			$height = $height_max = 355;

			if (
				!empty( $argv['width'] ) &&
				filter_var( $argv['width'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) ) &&
				$argv['width'] <= $width_max
			)
			{
				$width = $argv['width'];
			}
			if (
				!empty( $argv['height'] ) &&
				filter_var( $argv['height'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) ) &&
				$argv['height'] <= $height_max
			)
			{
				$height = $argv['height'];
			}

			$urlBase = '//www.youtube.com/v/';
			if ( !empty( $ytid ) ) {
				$url = $urlBase . $ytid;
				return "<object type=\"application/x-shockwave-flash\" data=\"{$url}\" width=\"{$width}\" height=\"{$height}\"><param name=\"movie\" value=\"{$url}\"/><param name=\"wmode\" value=\"transparent\"/></object>";
			}
		} else {
			// If the type argument wasn't supplied, default to HTML5, since that's
			// what YouTube offers by default as well
			$width = 560;
			$height = 315;
			$maxWidth = 960;
			$maxHeight = 720;

			if (
				!empty( $argv['width'] ) &&
				filter_var( $argv['width'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) ) &&
				$argv['width'] <= $maxWidth
			)
			{
				$width = $argv['width'];
			}
			if (
				!empty( $argv['height'] ) &&
				filter_var( $argv['height'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) ) &&
				$argv['height'] <= $maxHeight
			)
			{
				$height = $argv['height'];
			}

			// Support YouTube's "enhanced privacy mode", in which "YouTube won’t
			// store information about visitors on your web page unless they play
			// the video" if the privacy argument was supplied
			// @see https://support.google.com/youtube/answer/171780?expand=PrivacyEnhancedMode#privacy
			$urlBase = '//www.youtube-nocookie.com/embed/';

			if ( !empty( $ytid ) ) {
				$url = $urlBase . $ytid;
				return "<iframe width=\"{$width}\" height=\"{$height}\" src=\"{$url}\" frameborder=\"0\" allowfullscreen></iframe>";
			}
		}
	}

	public static function url2gvid( $url ) {
		$id = $url;

		if ( preg_match( '/^http:\/\/video\.google\.com\/videoplay\?docid=([^&]+)(&hl=.+)?$/', $url, $preg ) ) {
			$id = $preg[1];
		} elseif ( preg_match( '/^http:\/\/video\.google\.com\/googleplayer\.swf\?docId=(.+)$/', $url, $preg ) ) {
			$id = $preg[1];
		}

		preg_match( '/([0-9-]+)/', $id, $preg );
		$id = $preg[1];

		return $id;
	}

	public static function embedGoogleVideo( $input, $argv, $parser ) {
		$gvid   = '';
		$width  = $width_max  = 400;
		$height = $height_max = 326;

		if ( !empty( $argv['gvid'] ) ) {
			$gvid = self::url2gvid( $argv['gvid'] );
		} elseif ( !empty( $input ) ) {
			$gvid = self::url2gvid( $input );
		}
		if ( !empty( $argv['width'] ) && settype( $argv['width'], 'integer' ) && ( $width_max >= $argv['width'] ) ) {
			$width = $argv['width'];
		}
		if ( !empty( $argv['height'] ) && settype( $argv['height'], 'integer' ) && ( $height_max >= $argv['height'] ) ) {
			$height = $argv['height'];
		}

		if ( !empty( $gvid ) ) {
			$url = "http://video.google.com/googleplayer.swf?docId={$gvid}";
			return "<object type=\"application/x-shockwave-flash\" data=\"{$url}\" width=\"{$width}\" height=\"{$height}\"><param name=\"movie\" value=\"{$url}\"/><param name=\"wmode\" value=\"transparent\"/></object>";
		}
	}

	public static function url2aovid( $url ) {
		$id = $url;

		if ( preg_match( '/http:\/\/www\.archive\.org\/download\/(.+)\.flv$/', $url, $preg ) ) {
			$id = $preg[1];
		}

		preg_match( '/([0-9A-Za-z_\/.]+)/', $id, $preg );
		$id = $preg[1];

		return $id;
	}

	public static function embedArchiveOrgVideo( $input, $argv, $parser ) {
		$aovid   = '';
		$width  = $width_max  = 320;
		$height = $height_max = 263;

		if ( !empty( $argv['aovid'] ) ) {
			$aovid = self::url2aovid( $argv['aovid'] );
		} elseif ( !empty( $input ) ) {
			$aovid = self::url2aovid( $input );
		}
		if (
			!empty( $argv['width'] ) &&
			settype( $argv['width'], 'integer' ) &&
			( $width_max >= $argv['width'] )
		)
		{
			$width = $argv['width'];
		}
		if (
			!empty( $argv['height'] ) &&
			settype( $argv['height'], 'integer' ) &&
			( $height_max >= $argv['height'] )
		)
		{
			$height = $argv['height'];
		}

		if ( !empty( $aovid ) ) {
			$url = "http://www.archive.org/download/{$aovid}.flv";
			return "<object type=\"application/x-shockwave-flash\" data=\"http://www.archive.org/flv/FlowPlayerWhite.swf\" width=\"{$width}\" height=\"{$height}\"><param name=\"movie\" value=\"http://www.archive.org/flv/FlowPlayerWhite.swf\"/><param name=\"flashvars\" value=\"config={loop: false, videoFile: '{$url}', autoPlay: false}\"/></object>";
		}
	}

	public static function url2aoaid( $url ) {
		$id = $url;

		if ( preg_match( '/http:\/\/www\.archive\.org\/details\/(.+)$/', $url, $preg ) ) {
			$id = $preg[1];
		}

		preg_match( '/([0-9A-Za-z_\/.]+)/', $id, $preg );
		$id = $preg[1];

		return $id;
	}

	public static function embedArchiveOrgAudio( $input, $argv, $parser ) {
		$aoaid   = '';
		$width  = $width_max  = 400;
		$height = $height_max = 170;

		if ( !empty( $argv['aoaid'] ) ) {
			$aoaid = self::url2aoaid( $argv['aoaid'] );
		} elseif ( !empty( $input ) ) {
			$aoaid = self::url2aoaid( $input );
		}
		if (
			!empty( $argv['width'] ) &&
			settype( $argv['width'], 'integer' ) &&
			( $width_max >= $argv['width'] )
		)
		{
			$width = $argv['width'];
		}
		if (
			!empty( $argv['height'] ) &&
			settype( $argv['height'], 'integer' ) &&
			( $height_max >= $argv['height'] )
		)
		{
			$height = $argv['height'];
		}

		if ( !empty( $aoaid ) ) {
			$url = urlencode( "http://www.archive.org/audio/xspf-maker.php?identifier={$aoaid}" );
			return "<object type=\"application/x-shockwave-flash\" data=\"http://www.archive.org/audio/xspf_player.swf?playlist_url={$url}\" width=\"{$width}\" height=\"{$height}\"><param name=\"movie\" value=\"http://www.archive.org/audio/xspf_player.swf?playlist_url={$url}\"/></object>";
		}
	}

	public static function url2weid( $url ) {
		$id = $url;

		if ( preg_match( '/^http:\/\/www\.wegame\.com\/watch\/(.+)\/$/', $url, $preg ) ) {
			$id = $preg[1];
		}

		preg_match( '/([0-9A-Za-z_-]+)/', $id, $preg );
		$id = $preg[1];

		return $id;
	}

	public static function embedWeGame( $input, $argv, $parser ) {
		$weid   = '';
		$width  = $width_max  = 488;
		$height = $height_max = 387;

		if ( !empty( $argv['weid'] ) ) {
			$weid = self::url2weid( $argv['weid'] );
		} elseif ( !empty( $input ) ) {
			$weid = self::url2weid( $input );
		}
		if (
			!empty( $argv['width'] ) &&
			settype( $argv['width'], 'integer' ) &&
			( $width_max >= $argv['width'] )
		)
		{
			$width = $argv['width'];
		}
		if (
			!empty( $argv['height'] ) &&
			settype( $argv['height'], 'integer' ) &&
			( $height_max >= $argv['height'] )
		)
		{
			$height = $argv['height'];
		}

		if ( !empty( $weid ) ) {
			return "<object type=\"application/x-shockwave-flash\" data=\"http://www.wegame.com/static/flash/player2.swf\" width=\"{$width}\" height=\"{$height}\"><param name=\"flashvars\" value=\"tag={$weid}\"/></object>";
		}
	}

	public static function url2tgid( $input ) {
		$tid = $gid = 0;

		if ( preg_match( '/^id=([0-9]+)\|gId=([0-9]+)$/i', $input, $preg ) ) {
			$tid = $preg[1];
			$gid = $preg[2];
		} elseif ( preg_match( '/^gId=([0-9]+)\|id=([0-9]+)$/i', $input, $preg ) ) {
			$tid = $preg[2];
			$gid = $preg[1];
		} elseif ( preg_match( '/^([0-9]+)\|([0-9]+)$/', $input, $preg ) ) {
			$tid = $preg[1];
			$gid = $preg[2];
		}

		return array( $tid, $gid );
	}

	public static function embedTangler( $input, $argv, $parser ) {
		$tid = $gid = '';

		if ( !empty( $argv['tid'] ) && !empty( $argv['gid'] ) ) {
			list( $tid, $gid ) = self::url2tgid( "{$argv['tid']}|{$argv['gid']}" );
		} elseif ( !empty( $input ) ) {
			list( $tid, $gid ) = self::url2tgid( $input );
		}

		if ( !empty( $tid ) && !empty( $gid ) ) {
			return "<p style=\"width: 410px; height: 480px\" id=\"tangler-embed-topic-{$tid}\"></p><script src=\"http://www.tangler.com/widget/embedtopic.js?id={$tid}&gId={$gid}\"></script>";
		}
	}

	public static function url2gtid( $url ) {
		$id = $url;

		if ( preg_match( '/^http:\/\/www\.gametrailers\.com\/player\/(.+)\.html$/', $url, $preg ) ) {
			$id = $preg[1];
		} elseif ( preg_match( '/^http:\/\/www\.gametrailers\.com\/remote_wrap\.php\?mid=(.+)$/', $url, $preg ) ) {
			$id = $preg[1];
		}

		preg_match( '/([0-9]+)/', $id, $preg );
		$id = $preg[1];

		return $id;
	}

	public static function embedGametrailers( $input, $argv, $parser ) {
		$gtid   = '';
		$width  = $width_max  = 480;
		$height = $height_max = 392;

		if ( !empty( $argv['gtid'] ) ) {
			$gtid = self::url2gtid( $argv['gtid'] );
		} elseif ( !empty( $input ) ) {
			$gtid = self::url2gtid( $input );
		}
		if (
			!empty( $argv['width'] ) &&
			settype( $argv['width'], 'integer' ) &&
			( $width_max >= $argv['width'] )
		)
		{
			$width = $argv['width'];
		}
		if (
			!empty( $argv['height'] ) &&
			settype( $argv['height'], 'integer' ) &&
			( $height_max >= $argv['height'] )
		)
		{
			$height = $argv['height'];
		}

		if ( !empty( $gtid ) ) {
			$url = "http://www.gametrailers.com/remote_wrap.php?mid={$gtid}";
			// return "<object type=\"application/x-shockwave-flash\" width=\"{$width}\" height=\"{$height}\"><param name=\"movie\" value=\"{$url}\"/></object>";
			// gametrailers' flash doesn't work on FF with object tag alone )-: weird, yt and gvideo are ok )-: valid xhtml no more )-:
			return "<object classid=\"clsid:d27cdb6e-ae6d-11cf-96b8-444553540000\"  codebase=\"http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=8,0,0,0\" id=\"gtembed\" width=\"{$width}\" height=\"{$height}\">	<param name=\"allowScriptAccess\" value=\"sameDomain\" /> 	<param name=\"allowFullScreen\" value=\"true\" /> <param name=\"movie\" value=\"{$url}\"/> <param name=\"quality\" value=\"high\" /> <embed src=\"{$url}\" swLiveConnect=\"true\" name=\"gtembed\" align=\"middle\" allowScriptAccess=\"sameDomain\" allowFullScreen=\"true\" quality=\"high\" pluginspage=\"http://www.macromedia.com/go/getflashplayer\" type=\"application/x-shockwave-flash\" width=\"{$width}\" height=\"{$height}\"></embed> </object>";
		}
	}

	public static function url2nvid( $url ) {
		$id = $url;

		preg_match( '/([0-9A-Za-z]+)/', $id, $preg );
		$id = $preg[1];

		return $id;
	}

	public static function embedNicovideo( $input, $argv, $parser ) {
		$nvid = '';

		if ( !empty( $argv['nvid'] ) ) {
			$nvid = self::url2nvid( $argv['nvid'] );
		} elseif ( !empty( $input ) ) {
			$nvid = self::url2nvid( $input );
		}

		if ( !empty( $nvid ) ) {
			$url = "http://ext.nicovideo.jp/thumb_watch/{$nvid}";
			return "<script type=\"text/javascript\" src=\"{$url}\"></script>";
		}
	}

	public static function url2ggid( $url ) {
		$id = $url;

		if ( preg_match( '/^http:\/\/www\.gogreentube\.com\/watch\.php\?v=(.+)$/', $url, $preg ) ) {
			$id = $preg[1];
		} elseif ( preg_match( '/^http:\/\/www\.gogreentube\.com\/embed\/(.+)$/', $url, $preg ) ) {
			$id = $preg[1];
		}

		preg_match( '/([0-9A-Za-z]+)/', $id, $preg );
		$id = $preg[1];

		return $id;
	}

	public static function embedGoGreenTube( $input, $argv, $parser ) {
		$ggid = '';
		$width  = $width_max  = 432;
		$height = $height_max = 394;

		if ( !empty( $argv['ggid'] ) ) {
			$ggid = self::url2ggid( $argv['ggid'] );
		} elseif ( !empty( $input ) ) {
			$ggid = self::url2ggid( $input );
		}

		if (
			!empty( $argv['width'] ) &&
			settype( $argv['width'], 'integer' ) &&
			( $width_max >= $argv['width'] )
		)
		{
			$width = $argv['width'];
		}

		if (
			!empty( $argv['height'] ) &&
			settype( $argv['height'], 'integer' ) &&
			( $height_max >= $argv['height'] )
		)
		{
			$height = $argv['height'];
		}

		if ( !empty( $ggid ) ) {
			$url = "http://www.gogreentube.com/embed/{$ggid}";
			return "<script type=\"text/javascript\" src=\"{$url}\"></script>";
		}
	}

}

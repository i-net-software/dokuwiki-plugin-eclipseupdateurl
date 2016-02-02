<?php
/**
 * eclipseUpdate Plugin
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_eclipseupdateurl extends DokuWiki_Syntax_Plugin {

    function getType() { return 'substition'; }
    function getPType() { return 'block'; }
    function getSort() { return 99; }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern("\[\[eclipseUpdate>.*?\]\]", $mode, 'plugin_eclipseupdateurl');
    }

    function handle($match, $state, $pos, Doku_Handler $handler) {

		$ID = substr($match, 16, -2);
		list( $ID, $opts) = explode( '|', $ID, 2 );
		
		$opts = explode( '|', $opts );
		$fOpts = array();
		$fOpts['amount'] = $this->getConf('showamount');
		
		foreach ( $opts as $opt ) {
		
			if ( empty($opt) ) continue;
			if ( is_numeric($opt) ) {
				$fOpts['amount'] = $opt;
			} else {
				list($key, $value) = explode('=', $opt, 2);
				
				switch ( $key ) {
					case 'direct' :		$fOpts['direct'] = true;
										break;
					case 'name' :		$fOpts['name'] = $value;
										break;
					case 'category' :	$fOpts['category'] = $value;
										break;
					case 'id' :			$fOpts['id'] = $value;
										break;
				}
			}
		}
		
		
		return array( $ID, $fOpts );
    }            
	
    function render($mode, Doku_Renderer $renderer, $data) {
	
		list ( $data, $opts ) = $data;
		if ( $mode == 'xhtml' ) {
			
			if ( !preg_match("/site.xml$/", $data) ) {
				msg("'$data' is not a valid site.xml for Eclipse Updates");
				return false;
			}
			
			if ( !preg_match( "&^(?i)https?://&", $data ) ) {
				$fn = mediaFN($data);
				if ( !file_exists($fn) ) {
					msg("'$data' does not exist");
					return false;
				}
				
				$fn = 'file://' . $fn;
			} else {
				$fn = $data;
			}
			
			list ($PLUGINDESC, $FEATURELIST) = $this->_getSiteXML($fn);
			
			if ( $opts['direct'] ) {
			
				// Selected Category or first
				$ITEMS = $opts['category'] ? $FEATURELIST[$opts['category']] : array_shift($FEATURELIST);
				
				// Latest
				$ITEM = $ITEMS['features'][count($ITEMS['features'])-1];
				
				// Plugin Element
				$PLUGIN = $ITEM['list'][0];
				
				if ( $opt['id'] ) {
					foreach( $ITEM['list'] as $PLUGIN ) {
						if ( $PLUGIN['id'] != $opt['id'] ) { continue; }
						break;
					}
				}

				// Name
				$NAME = $opts['name'] ? $opts['name'] : $PLUGIN['id'] . '_' . $PLUGIN['version'] . '.jar';

				//link
				$renderer->doc .= $this->tpl_link($ITEM['url'] . 'plugins/' .  $PLUGIN['id'] . '_' . $PLUGIN['version'] . '.jar', $NAME);
			
				return true;
			}
			
			$this->_getEclipseList($renderer, $PLUGINSDESC, $FEATURELIST);

		}
		
		return true;
	}
	
	function _getEclipseList(&$renderer, $PLUGINDESC, $FEATURELIST) {
	
		$renderer->doc .= "<p>$PLUGINDESC</p>";
		foreach ( $FEATURELIST as $CATEGORY => $ITEMS ) {
			
			$renderer->doc .= '<strong>' . $CATEGORY . '</strong>';
			
			$renderer->doc .= '<table class="eclipsepluginurl inline">';
			
			$renderer->doc .= '<tr>';
				$renderer->doc .= '<th>Version</th>';
				$renderer->doc .= '<th>Component</th>';
				$renderer->doc .= '<th>Size</th>';
			$renderer->doc .= '</tr>';
			
			foreach ( array_reverse(array_slice($ITEMS['features'], -$opts['amount'])) as $ITEM ) {
			
				if ( !is_array($ITEM['list']) )
					$ITEM['list'] = array($ITEM['list']);
			
				$renderer->doc .= '<tr>';
					$renderer->doc .= '<td rowspan="' . count($ITEM['list']) . '">' . $ITEM['version'] . '</td>';
					$renderer->doc .= '<td>';
					
					foreach ( $ITEM['list'] as $PLUGIN ) {
						if ( count($ITEM['list']) > 1 && $PLUGIN != $ITEM['list'][0] ) 
							$renderer->doc .= '</td></tr><tr><td>';
						
						$renderer->doc .= $this->tpl_link($ITEM['url'] . 'plugins/' .  $PLUGIN['id'] . '_' . $PLUGIN['version'] . '.jar', $PLUGIN['id'] . '_' . $PLUGIN['version'] . '.jar');

						$renderer->doc .= '</td><td>';
						
						$renderer->doc .= sprintf("%.2f&nbsp;MB", ($PLUGIN['download-size'] / 1024));
						$renderer->doc .= '</td>';
					}

				$renderer->doc .= '</tr>';
			}
			
			$renderer->doc .= '</table>';
		}
	}
	

	function tpl_link($url,$name,$more=''){
	
		$return = '<a href="'.$url.'" ';
		if ($more) $return .= ' '.$more;
		$return .= ">$name</a>";
		return $return;
	}

	function _getSiteXML($URI) {

		$reader = new XMLReader();
		$reader->open($URI);
		
		$PLUGINURL = null;
		$PLUGINDESC = null;
		$PLUGINLIST = array();
		
		$FEATURE = null;
		
		while ($reader->read()) {
		
			if ( $reader->nodeType !== XMLReader::ELEMENT ) { continue; }

			switch ($reader->name) {
			
				case 'description':		$PLUGINURL = $reader->getAttribute('url');
										$PLUGINDESC = $reader->readString();
										break;

				case 'feature' :		$FEATURE = $this->_getPluginURL($PLUGINURL . $reader->getAttribute('url'));
				
										if ( $URL === false ) { $FEATURE = null; }

										$FEATURE['id'] = $reader->getAttribute('id');
										$FEATURE['version'] = $reader->getAttribute('version');

										break;

				case 'category' :		if ( empty($FEATURE) ) { continue; }
										$CATEGORY = $reader->getAttribute('name');
										
										// Add category
										if ( empty( $PLUGINLIST[$CATEGORY] ) )
											$PLUGINLIST[$CATEGORY] = array	(
																				'label' => null,
																				'features' => array(),
																			);
										
										// Put feature and reset
										$PLUGINLIST[$CATEGORY]['features'][] = $FEATURE;
										$FEATURE = null;
										
										break;

				case 'category-def' :	$CATEGORY = $reader->getAttribute('name');
										$LABEL = $reader->getAttribute('label');
										
										if ( !empty( $PLUGINLIST[$CATEGORY] ) )
											$PLUGINLIST[$CATEGORY]['label'] = $LABEL;
										
										break;
			}
		}
		
		return array ($PLUGINDESC, $PLUGINLIST);
	}

	function _getFeatureXML($STRING) {

		if ( empty($STRING) ) return false;

		$reader = new XMLReader();
		$reader->XML($STRING);
		
		$PLUGINURL = null;
		$PLUGINDESC = null;
		$PLUGINLIST = array();
		
		while ($reader->read()) {
		
			if ( $reader->nodeType !== XMLReader::ELEMENT ) { continue; }

			switch ($reader->name) {
				case 'update'	:	$PLUGINURL = $reader->getAttribute('url');
									$PLUGINDESC = $reader->getAttribute('label');
									break;
				
				case 'plugin'	:	$PLUGINLIST[] = array	(
														'id' => $reader->getAttribute('id'), 
														'download-size' => $reader->getAttribute('download-size'), 
														'install-size' => $reader->getAttribute('install-size'), 
														'version' => $reader->getAttribute('version'), 
													);
									break;

				default			: 	continue;
			}
		}
		
		return array ( 'url' => $PLUGINURL, 'desc' => $PLUGINDESC, 'list' => $PLUGINLIST);
	}
	
	function _getPluginURL($FEATUREURL) {
		global $conf;

		$CACHE  = $conf['cachetime'];
		$FILE = $this->media_get_from_URL($FEATUREURL,'jar',$CACHE);
		
		if ( $FILE === false ) {
			return false;
		}
	
		return $this->_getFeatureXML($this->__fileExistsInJar($FILE, 'feature.xml', true));
	}
	
	/**
	* Download jar files
	*
	* @author Andreas Gohr <andi@splitbrain.org>
	*/
	function media_get_from_URL($url,$ext,$cache) {
		global $conf;
 
		// if no cache or fetchsize just redirect
		if ($cache==0)           return false;
		if (!$conf['fetchsize']) return false;
		
		$local = getCacheName(strtolower($url),".media.$ext");
		$mtime = @filemtime($local); // 0 if not exists

		//decide if download needed:
		if( ($mtime == 0) ||                           // cache does not exist
			($cache != -1 && $mtime < time()-$cache)   // 'recache' and cache has expired
		){
			if($this->media_download($url,$local)){
				return $local;
			}else{
				return false;
			}
		}

		//if cache exists use it else
		if($mtime) return $local;

		//else return false
		return false;
	}
	
	/**
	 * Download image files
	 *
	 * @author Andreas Gohr <andi@splitbrain.org>
	 */
	function media_download($url,$file){
		global $conf;

		//print $url;
		
		$http = new DokuHTTPClient();
		$http->max_bodysize = $conf['fetchsize'];
		$http->timeout = 25; //max. 25 sec
		
		$data = $http->get($url);
		if(!$data) return false;

		$fileexists = @file_exists($file);
		$fp = @fopen($file,"w");
		if(!$fp) return false;
		fwrite($fp,$data);
		fclose($fp);
		if(!$fileexists and $conf['fperm']) chmod($file, $conf['fperm']);

		// check if it is really a zip
		$info = @getimagesize($file);
		if(!$this->__fileExistsInJar($file, 'feature.xml')){
			@unlink($file);
			return false;
		}

		return true;
	}

	function __fileExistsInJar($JAR, $NAME, $GET = false) {

		if ( empty( $JAR ) ) return;

		$zip = new ZipArchive;
		$code = $zip->open($JAR);
		if ($code === TRUE && !($zip->statName($NAME) === FALSE)) {
			
			return $GET ? $zip->getFromName($NAME) : TRUE;
		}
		
		return false;
	}
}
// vim:ts=4:sw=4:et:enc=utf-8: 

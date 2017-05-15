<?php
/**
 * Renderer for text output
 */

if(!defined('DOKU_INC')) define('DOKU_INC',fullpath(dirname(__FILE__).'/../../').'/');

if ( !defined('SET_LF') ) {
    define ('SET_LF',"&#xA;");
}

require_once DOKU_INC . 'inc/parser/renderer.php';

class renderer_plugin_settingsbundle extends Doku_Renderer {

    // @access public
    private $doc = '';        // will contain the whole document
    private $toc = array();   // will contain the Table of Contents

    private $footnotes = array();
    private $store = '';
    private $nSpan = 0;
    private $separator = '';

    function getFormat(){
        return 'settings_bundle';
    }

    //handle plugin rendering
    function plugin($name,$data){
        $plugin =& plugin_load('syntax',$name);
        if($plugin != null){
            if(!$plugin->render($this->getFormat(),$this,$data)) {

              // probably doesn't support text, so use stripped-down xhtml
              $tmpData = $this->doc;
              $this->doc = '';
              if($plugin->render('xhtml',$this,$data) && ($this->doc != '')) {
                $search = array('@<script[^>]*?>.*?</script>@si', // javascript
                  '@<style[^>]*?>.*?</style>@siU',                // style tags
                  '@<[\/\!]*?[^<>]*?>@si',                        // HTML tags
                  '@<![\s\S]*?--[ \t\n\r]*>@',                    // multi-line comments
                  '@\s+@'                                         // extra whitespace
                  );
                $this->doc = $tmpData . trim(html_entity_decode(preg_replace($search,' ',$this->doc),ENT_QUOTES));
              }
              else
                $this->doc = $tmpData;
            }
        }
    }

    function document_start() {
        global $ID;

        $this->doc = '';
        $toc = array();
        $footnotes = array();
        $store = '';
        $nSpan = 0;
        $separator = '';

        $metaheader = array();
        $metaheader['Content-Type'] = 'text/html; charset=utf-8';
        //$metaheader['Content-Disposition'] = 'attachment; filename="noname.txt"';
        $meta = array();
        $meta['format']['text'] = $metaheader;
        p_set_metadata($ID,$meta);
    }

    function document_end() {
        if ( count ($this->footnotes) > 0 ) {
            $this->doc .= SET_LF;

            $id = 0;
            foreach ( $this->footnotes as $footnote ) {
                $id++;   // the number of the current footnote

                // check its not a placeholder that indicates actual footnote text is elsewhere
                if (substr($footnote, 0, 5) != "@@FNT") {
                    $this->doc .= $id.') ';
                    // get any other footnotes that use the same markup
                    $alt = array_keys($this->footnotes, "@@FNT$id");
                    if (count($alt)) {
                      foreach ($alt as $ref) {
                        $this->doc .= ($ref+1).') ';
                      }
                    }
                    $this->doc .= $footnote . SET_LF;
                }
            }
        }

        // Prepare the TOC
        if($this->info['toc'] && is_array($this->toc) && count($this->toc) > 2){
            global $TOC;
            $TOC = $this->toc;
        }

        // make sure there are no empty paragraphs
        $this->doc = preg_replace('%'.SET_LF.'\s*'.SET_LF.'\s*'.SET_LF.'%',SET_LF.SET_LF,$this->doc);
    }

    function header($text, $level, $pos) {
        $this->doc .= SET_LF.$text.SET_LF;
    }

    function section_close() {
        $this->doc .= SET_LF;
    }

    function cdata($text) {
        $this->doc .= $text;
    }

    function p_close() {
        $this->doc .= SET_LF;
    }

    function linebreak() {
        $this->doc .= SET_LF;
    }

    function hr() {
        $this->doc .= SET_LF;
    }

    function strong_open() {
        $this->doc .= ' ';
    }

    function strong_close() {
        $this->doc .= ' ';
    }

    /**
     * Callback for footnote start syntax
     *
     * All following content will go to the footnote instead of
     * the document. To achieve this the previous rendered content
     * is moved to $store and $doc is cleared
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function footnote_open() {

        // move current content to store and record footnote
        $this->store = $this->doc;
        $this->doc   = '';
    }

    /**
     * Callback for footnote end syntax
     *
     * All rendered content is moved to the $footnotes array and the old
     * content is restored from $store again
     *
     * @author Andreas Gohr
     */
    function footnote_close() {

        // recover footnote into the stack and restore old content
        $footnote = $this->doc;
        $this->doc = $this->store;
        $this->store = '';

        // check to see if this footnote has been seen before
        $i = array_search($footnote, $this->footnotes);

        if ($i === false) {
            // its a new footnote, add it to the $footnotes array
            $id = count($this->footnotes)+1;
            $this->footnotes[count($this->footnotes)] = $footnote;
        } else {
            // seen this one before, translate the index to an id and save a placeholder
            $i++;
            $id = count($this->footnotes)+1;
            $this->footnotes[count($this->footnotes)] = "@@FNT".($i);
        }

        // output the footnote reference and link
        $this->doc .= ' '.$id.')';
    }

    function listu_close() {
        $this->doc .= SET_LF;
    }

    function listcontent_close() {
        $this->doc .= SET_LF;
    }

    function unformatted($text) {
        $this->doc .= $text;
    }

    function php($text) {
    }

    function phpblock($text) {
        $this->doc .= $text;
    }

    function html($text) {
        $this->doc .= strip_tags($text);
    }

    function htmlblock($text) {
        $this->html($text);
    }

    function preformatted($text) {
        $this->doc .= $text.SET_LF;
    }

    function file($text) {
        $this->doc .= $text.SET_LF;
    }

    function code($text, $language = NULL) {
        $this->preformatted($text);
    }

    function rss($url, $params) {
        $this->preformatted($url);
    }

    function acronym($acronym) {
        if ( array_key_exists($acronym, $this->acronyms) ) {
            $title = $this->acronyms[$acronym];
            $this->doc .= ' ' . $acronym.' ('.$title.') ';
        } else {
            $this->doc .= ' ' . $acronym . ' ';
        }
    }

    function smiley($smiley) {
        $this->doc .= $smiley;
    }

    function entity($entity) {
        if ( array_key_exists($entity, $this->entities) ) {
            $this->doc .= $this->entities[$entity];
        } else {
            $this->doc .= $entity;
        }
    }

    function multiplyentity($x, $y) {
        $this->doc .= $x.'x'.$y;
    }

    function singlequoteopening() {
        global $lang;
        $this->doc .= $lang['singlequoteopening'];
    }

    function singlequoteclosing() {
        global $lang;
        $this->doc .= $lang['singlequoteclosing'];
    }

    function apostrophe() {
        global $lang;
        $this->doc .= $lang['apostrophe'];
    }

    function doublequoteopening() {
        global $lang;
        $this->doc .= $lang['doublequoteopening'];
    }

    function doublequoteclosing() {
        global $lang;
        $this->doc .= $lang['doublequoteclosing'];
    }

    function camelcaselink($link) {
      $this->internallink($link,$link);
    }

    function locallink($hash, $name = NULL){
        $name  = $this->_getLinkTitle($name, $hash, $isImage);
        $this->doc .= ' ' . $name . ' ';
    }

    function internallink($id, $name = NULL, $search=NULL,$returnonly=false) {
        global $ID;
        // default name is based on $id as given
        $default = $this->_simpleTitle($id);
        resolve_pageid(getNS($ID),$id,$exists);
        $this->doc .= ' ' . $this->_getLinkTitle($name, $default, $isImage, $id) . ' ';
    }

    function externallink($url, $name = NULL) {
        $this->doc .= ' ' . $this->_getLinkTitle($name, $url, $isImage) . ' ';
    }

    function interwikilink($match, $name = NULL, $wikiName, $wikiUri) {
        $this->doc .= ' ' . $this->_getLinkTitle($name, $wikiUri, $isImage) . ' ';
    }

    function windowssharelink($url, $name = NULL) {
        $this->doc .= ' ' . $this->_getLinkTitle($name, $url, $isImage) . ' ';
    }

    function emaillink($address, $name = NULL) {
        $name = $this->_getLinkTitle($name, '', $isImage);
        $address = html_entity_decode(obfuscate($address),ENT_QUOTES,'UTF-8');
        if(empty($name)){
            $name = $address;
        }
        $this->doc .= ' ' . $name . ' ';
    }

    function internalmedia ($src, $title=NULL, $align=NULL, $width=NULL,
                            $height=NULL, $cache=NULL, $linking=NULL) {
        $this->doc .= ' ' . $title . ' ';
    }

    function externalmedia ($src, $title=NULL, $align=NULL, $width=NULL,
                            $height=NULL, $cache=NULL, $linking=NULL) {
        $this->doc .= ' ' . $title . ' ';
    }

    function table_close(){
        $this->doc .= SET_LF;
    }

    function tablerow_open(){
        $this->separator = '';
    }

    function tablerow_close(){
        $this->doc .= SET_LF;
    }

    function tableheader_open($colspan = 1, $align = NULL){
        $this->tablecell_open();
    }

    function tableheader_close(){
        $this->tablecell_close();
    }

    function tablecell_open($colspan = 1, $align = NULL){
        $this->nSpan = $colspan;
        $this->doc .= $this->separator;
        $this->separator = ', ';
    }

    function tablecell_close(){
        if ( $this->nSpan > 1 )
            $this->doc .= str_repeat(',',$this->nSpan-1);
        $this->nSpan = 0;
    }

    /**
     * Creates a linkid from a headline
     *
     * @param string  $title   The headline title
     * @param boolean $create  Create a new unique ID?
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function _headerToLink($title,$create=false) {
        $title = str_replace(':','',cleanID($title));
        $title = ltrim($title,'0123456789._-');
        if(empty($title)) $title='section';

        return $title;
    }

    function _getLinkTitle($title, $default, & $isImage, $id=NULL) {
        global $conf;

        $isImage = false;
        if ( is_null($title) ) {
            if ($conf['useheading'] && $id) {
                $heading = p_get_first_heading($id,true);
                if ($heading) {
                    return $heading;
                }
            }
            return $default;
        } else if ( is_string($title) ) {
            return $title;
        } else if ( is_array($title) ) {
            return $title['title'];
        }
    }

    function _xmlEntities($string) {
        return $string; // nothing to do for text
    }

    function _formatLink($link){
        if(!empty($link['name']))
          return $link['name'];
        elseif(!empty($link['title']))
          return $link['title'];
          return $link['url'];
    }
}

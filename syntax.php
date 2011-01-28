<?php
/**
 * Allows markup syntax in the header
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_header2 extends DokuWiki_Syntax_Plugin {

    function getType() { return 'baseonly';}
    function getPType() { return 'block';}
    function getAllowedTypes() { return array('formatting', 'substition', 'disabled'); }
    function getSort() { return 49; }

    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addEntryPattern("[ \t]*={2,}(?=[^\n]+={2,}[ \t]*\n)", $mode, 'plugin_header2');
    }

    function postConnect() {
        $this->Lexer->addExitPattern('={2,}[ \t]*(?=\n)', 'plugin_header2');
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler){
        switch ($state) {
            case DOKU_LEXER_ENTER :
                $this->h_level = 7 - strspn($match,"=");
                $this->h_pos = $pos;
                if ($handler->status['section']) $handler->_addCall('section_close',array(),$pos);
                $handler->addPluginCall('header2',array($state),$state,$pos,$match);
                $handler->CallWriter = & new Doku_Handler_Nest($handler->CallWriter,'nest_close');
                return false;
            case DOKU_LEXER_UNMATCHED :
                $handler->_addCall('cdata', array($match), $pos);
                return false;
            case DOKU_LEXER_EXIT :
                $handler->_addCall('nest_close', array(), $pos);
                $handler->CallWriter->process();
                $handler->CallWriter = & $handler->CallWriter->CallWriter;
                $handler->addPluginCall('header2',array($state,$this->h_level,$this->h_pos),$state,$pos,$match);
                $handler->_addCall('section_open',array($this->h_level),$pos);
                $handler->status['section'] = true;
                return false;
        }
        return false;
    }

    /**
     * Create output
     */
    function render($format, &$renderer, $data) {
        list($state,$level,$pos) = $data;
        switch ($state) {
            case DOKU_LEXER_ENTER :
                // store current parsed content
                $this->store = $renderer->doc;
                $renderer->doc  = '';
                // metadata renderer should always parse content in the header
                if ($format=='metadata') {
                    $this->capture = $renderer->capture;
                    $renderer->capture = true;
                }
                break;
            case DOKU_LEXER_EXIT :
                // retrieve content parsed by nest parser (i.e. in the header)
                $title = trim($renderer->doc);
                $renderer->doc = $this->store;
                $this->store = '';
                // restore variable
                if ($format=='metadata') {
                    $renderer->capture = $this->capture;
                }
                // create header
                if($level < 1) $level = 1;
                $method = '_' . $format . '_header';
                if (method_exists($this,$method)) {
                    // we have special procedure for the renderer
                    $this->$method($title, $level, $pos, $renderer);
                } else {
                    // fall back to default renderer behavior
                    $renderer->header($title,$level,$pos);
                }
                break;
        }
        return true;
    }

    // simple function to strip all html tags
    function _remove_html_tags($text) {
        return preg_replace( "#<[^>]*?>#", "" ,  $text);
    }

    /**
     * Revised procedures for renderers
     *
     * Basically adds &$renderer argument and replaces $this to $renderer,
     * and our procedures of course.
     */

    function _xhtml_header($text, $level, $pos, &$renderer) {
        global $conf;
        
        $displaytext = $text;  // <= added
        $text = htmlspecialchars_decode($this->_remove_html_tags($text),ENT_QUOTES);  // <= added
        if(!$text) return; //skip empty headlines

        $hid = $renderer->_headerToLink($text,true);

        //only add items within configured levels
        $renderer->toc_additem($hid, $text, $level);

        // adjust $node to reflect hierarchy of levels
        $renderer->node[$level-1]++;
        if ($level < $renderer->lastlevel) {
            for ($i = 0; $i < $renderer->lastlevel-$level; $i++) {
                $renderer->node[$renderer->lastlevel-$i-1] = 0;
            }
        }
        $renderer->lastlevel = $level;

        if ($level <= $conf['maxseclevel'] &&
            count($renderer->sectionedits) > 0 &&
            $renderer->sectionedits[count($renderer->sectionedits) - 1][2] === 'section') {
            $renderer->finishSectionEdit($pos - 1);
        }

        // write the header
        $renderer->doc .= DOKU_LF.'<h'.$level;
        if ($level <= $conf['maxseclevel']) {
            $renderer->doc .= ' class="' . $renderer->startSectionEdit($pos, 'section', $text) . '"';
        }
        $renderer->doc .= '><a name="'.$hid.'" id="'.$hid.'">';
        $renderer->doc .= $displaytext;  // <= revised
        $renderer->doc .= "</a></h$level>".DOKU_LF;
    }

    function _odt_header($text, $level, $pos, &$renderer){
        $displaytext = $text;  // <= added
        $text = $this->_remove_html_tags($text);  // <= added
        $hid = $renderer->_headerToLink($text,true);
        $renderer->doc .= '<text:h text:style-name="Heading_20_'.$level.'" text:outline-level="'.$level.'">';
        $renderer->doc .= '<text:bookmark-start text:name="'.$hid.'"/>';
        $renderer->doc .= $displaytext;  // <= revised
        $renderer->doc .= '<text:bookmark-end text:name="'.$hid.'"/>';
        $renderer->doc .= '</text:h>';
    }

    function _xml_header($text, $level, $pos, &$renderer){
        if (!$text) return; //skip empty headlines
        $renderer->nextHeader  = '<header level="' . $level . '" pos="' . $pos . '">'.
        $renderer->nextHeader .= $text;  // <= revised
        $renderer->nextHeader .= '</header>'.DOKU_LF;
    }
}

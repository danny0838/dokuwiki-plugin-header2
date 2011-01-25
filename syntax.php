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
                if ($format=='metadata') {
                    $renderer->capture = $this->capture;
                }
                // create header
                if($level < 1) $level = 1;
                $realtitle = $title;
                 // header() of metadata renderer will manage the escape later
                 // FIXME: xhtml for 'preview' toc; metadata renderer for 'show' toc, so they may dismatch
                if ($format == 'xhtml') $title = $this->_header_title_plain($realtitle);
                $renderer->header($title,$level,$pos,$realtitle);
                break;
        }
        return true;
    }

    // removes html tags, for toc
    function _header_title_plain($text) {
        return htmlspecialchars_decode(preg_replace( "#<[^>]*?>#", "" ,  $text),ENT_QUOTES);
    }
}

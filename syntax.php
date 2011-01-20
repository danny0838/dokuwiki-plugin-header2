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
    function getSort() { return 49; }

    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('[ \t]*={2,}[^\n]+={2,}[ \t]*(?=\n)', $mode, 'plugin_header2');
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler){
        // get level and title
        $title = trim($match);
        $level = 7 - strspn($title,'=');
        if($level < 1) $level = 1;
        $title = trim($title,'=');
        $title = trim($title);

        if ($handler->status['section']) $handler->_addCall('section_close',array(),$pos);
        $handler->addPluginCall('header2',array($title,$level,$pos),$state,$pos,$match);
        $handler->_addCall('section_open',array($level),$pos);
        $handler->status['section'] = true;
        return null;
    }

    /**
     * Create output
     */
    function render($format, &$renderer, $data) {
        list($title,$level,$pos) = $data;
        // metadata decides toc, which should match xhtml except tags
        if ($format == 'metadata') $format = 'xhtml';
        $realtitle = $this->_header_title_syntax($title,$format);
        $title = $this->_header_title_plain($realtitle);
        $renderer->header($title,$level,$pos,$realtitle);
        return true;
    }

    // no html in toc
    function _header_title_plain($text) {
        return htmlspecialchars_decode(preg_replace( "#<[^>]*?>#", "" ,  $text),ENT_QUOTES);
    }

    // refer to p_get_instructions()
    function _header_title_syntax($text,$format='xhtml') {
        $modes = p_get_parsermodes();
        // Create the parser
        $Parser = new Doku_Parser();
        // Add the Handler
        $Parser->Handler = new Doku_Handler();
        //add modes to parser
        foreach($modes as $mode){
            $Parser->addMode($mode['mode'],$mode['obj']);
        }
        // Do the parsing, force quote mode and clear the quote call to prevent block elements
        $text = ">".$text;
        $p = $Parser->parse($text);
        array_shift($p);
        array_shift($p);
        array_pop($p);
        array_pop($p);
        return p_render( $format, $p, $info);
    }
}

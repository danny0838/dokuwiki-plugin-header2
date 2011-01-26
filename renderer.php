<?php
/**
 * Renderer to support header2 syntax
 */

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once DOKU_INC . 'inc/parser/xhtml.php';

/**
 * The Renderer
 */
class renderer_plugin_header2 extends Doku_Renderer_xhtml {

    function canRender($format) {
        return ($format=='xhtml');
    }

    /**
     * $sectionedits is private and cannot be accessed by plugins,
     * so this dirty hack is required
     *
     * Get rid of this renderer if it's fixed...
     */
    public $sectionedits = array(); // A stack of section edit data

    /**
     * Copied from xhtml.php, no change
     */
    public function startSectionEdit($start, $type, $title = null) {
        static $lastsecid = 0;
        $this->sectionedits[] = array(++$lastsecid, $start, $type, $title);
        return 'sectionedit' . $lastsecid;
    }

    public function finishSectionEdit($end = null) {
        list($id, $start, $type, $title) = array_pop($this->sectionedits);
        if (!is_null($end) && $end <= $start) {
            return;
        }
        $this->doc .= "<!-- EDIT$id " . strtoupper($type) . ' ';
        if (!is_null($title)) {
            $this->doc .= '"' . str_replace('"', '', $title) . '" ';
        }
        $this->doc .= "[$start-" . (is_null($end) ? '' : $end) . '] -->';
    }

    function document_end() {
        // Finish open section edits.
        while (count($this->sectionedits) > 0) {
            if ($this->sectionedits[count($this->sectionedits) - 1][1] <= 1) {
                // If there is only one section, do not write a section edit
                // marker.
                array_pop($this->sectionedits);
            } else {
                $this->finishSectionEdit();
            }
        }

        if ( count ($this->footnotes) > 0 ) {
            $this->doc .= '<div class="footnotes">'.DOKU_LF;

            $id = 0;
            foreach ( $this->footnotes as $footnote ) {
                $id++;   // the number of the current footnote

                // check its not a placeholder that indicates actual footnote text is elsewhere
                if (substr($footnote, 0, 5) != "@@FNT") {

                    // open the footnote and set the anchor and backlink
                    $this->doc .= '<div class="fn">';
                    $this->doc .= '<sup><a href="#fnt__'.$id.'" id="fn__'.$id.'" name="fn__'.$id.'" class="fn_bot">';
                    $this->doc .= $id.')</a></sup> '.DOKU_LF;

                    // get any other footnotes that use the same markup
                    $alt = array_keys($this->footnotes, "@@FNT$id");

                    if (count($alt)) {
                      foreach ($alt as $ref) {
                        // set anchor and backlink for the other footnotes
                        $this->doc .= ', <sup><a href="#fnt__'.($ref+1).'" id="fn__'.($ref+1).'" name="fn__'.($ref+1).'" class="fn_bot">';
                        $this->doc .= ($ref+1).')</a></sup> '.DOKU_LF;
                      }
                    }

                    // add footnote markup and close this footnote
                    $this->doc .= $footnote;
                    $this->doc .= '</div>' . DOKU_LF;
                }
            }
            $this->doc .= '</div>'.DOKU_LF;
        }

        // Prepare the TOC
        global $conf;
        if($this->info['toc'] && is_array($this->toc) && $conf['tocminheads'] && count($this->toc) >= $conf['tocminheads']){
            global $TOC;
            $TOC = $this->toc;
        }

        // make sure there are no empty paragraphs
        $this->doc = preg_replace('#<p>\s*</p>#','',$this->doc);
    }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :

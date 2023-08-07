<?php
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_pubmed extends DokuWiki_Syntax_Plugin {
  var $ncbi;
  var $xmlCache;
  // Constructor
  function __construct(){
    if (!class_exists('plugin_cache'))
      @require_once(DOKU_PLUGIN.'pubmed/classes/cache.php');
    if (!class_exists('rcsb')||!class_exists('ncbi')||!class_exists('xml'))
      @require_once(DOKU_PLUGIN.'pubmed/classes/sciencedb.php');
    $this->ncbi     = new ncbi();
    $this->xmlCache = new plugin_cache("ncbi_esummary","pubmed","xml.gz");
    
    if($this->ncbi == Null) print("NCBI is null !");
    if($this->xmlCache == Null) print("this->xmlCache is null !");
  }
  function getType(){ return 'substition'; }
  function getSort(){ return 158; }
  function connectTo($mode){$this->Lexer->addSpecialPattern('\{\{pubmed>[^}]*\}\}',$mode,'plugin_pubmed');}
 /**
  * Handle the match
  */
  function handle($match, $state, $pos, Doku_Handler $handler){
    $match = substr($match,9,-2);
    if(str_contains($match,':')){
      list($cmd,$pmid) = explode(':',$match);
    }else{
        $cmd = $match;
        $pmid = '';
    }
    return array($state,array($cmd,$pmid));
  }
 /**
  * Create output
  */
  function render($mode, Doku_Renderer $renderer, $data) {
    if ($mode!='xhtml')
      return false;
    list($state, $query) = $data;
    list($cmd, $pmid) = $query;
    $cmd = strtolower($cmd);
    if ($cmd=='long' || $cmd=='short'){
      if (!is_numeric($pmid)){
        $renderer->doc.=sprintf($this->getLang('pubmed_wrong_format'));
        return false;
      }
      $xml = $this->getSummaryXML($pmid);
      if(empty($xml)){
        $renderer->doc.=sprintf($this->getLang('pubmed_not_found'),$pmid);
        return false;
      }
      $href_url = sprintf($this->ncbi->pubmedURL,$pmid);
      $journal = $this->ncbi->GetSummaryItem ("Source",$xml);
      $date    = $this->ncbi->GetSummaryItem ("PubDate",$xml);
      $volume  = $this->ncbi->GetSummaryItem ("Volume",$xml);
      $pages   = $this->ncbi->GetSummaryItem ("Pages",$xml);
      $authors = $this->ncbi->GetSummaryItems("Author",$xml);
      $title   = $this->ncbi->GetSummaryItem ("Title",$xml);

      if ($cmd=='long'||$cmd=='short'){
        $renderer->doc.='<div class="pubmed">';
        if ($cmd=='long'){
          $renderer->doc.= '<a href="'.$href_url.'">'.$title.'</a><br/>';
          $renderer->doc.= implode(', ',$authors).'<br/>';
          $renderer->doc.= '<span class="jrnl">'.$journal.'</span>';
        }elseif($cmd=='short'){
          if (count($authors)>1) $etal = '<span class="etal">et al.</span>';
          $renderer->doc.= '<a href="'.$href_url.'">'.$authors[0].$etal;
          $renderer->doc.= '<span class="jrnl">'.$journal.'</span></a>';
        }
        $renderer->doc.= '<span class="volume">'.$volume.'</span>';
        $renderer->doc.= '<span class="pages">p'.$pages.'</span>';
        $renderer->doc.= '<span class="date">('.$date.')</span></div>'.NL;
      }
    }else{
      switch($cmd){
        case 'summaryxml':
          if (!is_numeric($pmid)){
            $renderer->doc.=sprintf($this->getLang('pubmed_wrong_format'));
            return false;
          }
          $xml = $this->getSummaryXML($pmid);
          if(empty($xml)){
            $renderer->doc.=sprintf($this->getLang('pubmed_not_found'),$pmid);
            return false;
          }
          $renderer->doc .= "<pre>".htmlspecialchars($xml)."</pre>";
          return true;

        case 'clear_summary':
          $this->xmlCache->ClearCache();
          $renderer->doc .= 'Cleared.';
          return true;

        case 'remove_dir':
          $this->xmlCache->RemoveDir();
          $renderer->doc .= 'Directory cleared.';
          return true;

        default:
          // Command was not found..
          $renderer->doc.='<div class="pdb_plugin">'.sprintf($this->getLang('plugin_cmd_not_found'),$cmd).'</div>';
          $renderer->doc.='<div class="pdb_plugin_text">'.$this->getLang('pubmed_available_cmd').'</div>';
          return true;
          $renderer->doc.=sprintf($this->getLang('pubmed_wrong_format'));
          return true;
      }
    }
  }

 /**
  * Get summary XML from cache or NCBI
  */
    function getSummaryXml($pmid){
    global $conf;
    if($this->xmlCache == Null){
        print("Null !!");}
        
    $cachedXml = $this->xmlCache->GetMediaText($pmid);
    if ($cachedXml!==false){ return $cachedXml; }

    // Get summary XML
    $summary = $this->ncbi->SummaryXml('pubmed',$pmid);
    $cachePath = $this->xmlCache->GetMediaPath($pmid);
    if (!empty($summary)){
      if(io_saveFile($cachePath,$summary)){
        chmod($cachePath,$conf['fmode']);
      }
    }
    return $summary;
  }
}
// TODO: Implement search results !

?>

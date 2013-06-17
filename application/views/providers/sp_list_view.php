<div id="subtitle"><h3>
<?php 
  if($typesps === 'local')
  {
     echo lang('rr_tbltitle_listlocalsps');
  }
  elseif($typesps === 'external')
  {
     echo lang('rr_tbltitle_listextsps');
  }
  else
  {
     echo lang('rr_tbltitle_listsps');

  }
  
 echo ' (' . lang('rr_found') . ' ' . $sps_count . ')';


?>


</h3></div>

<?php
$form = '<div class="mobilehidden"><form id="filter-form">'. lang('rr_filter') .': <input name="filter" id="filter" value="" maxlength="30" size="30" type="text"></form></div>';
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
echo $form;

$prefurl = base_url().'providers/sp_list/';
?>
<div id="navbuttons1">
<?php
if($typesps === 'local')
{

?>
<button type="button" class="btn typelist" onclick="window.location.href='<?php echo $prefurl; ?>show/all'" >all providers</button>
<button type="button" class="btn typelist" onclick="window.location.href='<?php echo $prefurl; ?>show/ext'" >external/imported</button>
<button type="button" class="btn tchosen" disabled="disabled">locally managed</button>
<?php
}
elseif($typesps === 'external')
{
?>
<button type="button" class="btn typelist" onclick="window.location.href='<?php echo $prefurl; ?>show/all'" >all providers</button>
<button type="button" class="btn tchosen" disabled="disabled">external/imported</button>
<button type="button" class="btn typelist" onclick="window.location.href='<?php echo $prefurl; ?>show'" >locally managed</button>

<?php
}
elseif($typesps === 'all')
{
?>
<button type="button" class="btn tchosen" disabled="disabled">all providers</button>
<button type="button" class="btn typelist" onclick="window.location.href='<?php echo $prefurl; ?>show/ext'" >external/imported</button>
<button type="button" class="btn typelist" onclick="window.location.href='<?php echo $prefurl; ?>show'" >locally managed</button>

<?php
}
?>
</div>

<?php

$tmpl = array('table_open' => '<table  id="details" class="zebra splist">');

$this->table->set_template($tmpl);
$this->table->set_heading(lang('tbl_title_nameandentityid'),'#',lang('tbl_title_regdate'), lang('tbl_title_helpurl'));
#$this->table->set_caption(lang('rr_tbltitle_listsps').' ('.lang('rr_found').' '.$sps_count.')');
echo $this->table->generate($sprows);
$this->table->clear();
?>

<?php
  class Zasilkovna {
    var $code, $title, $description, $icon, $enabled, $api_key, $country;

// class constructor
    function zasilkovna() {
      global $order, $db;
      
      /** injected code cleanup (for mailing, etc) **/
      if(preg_match('/^Zásilkovna/', $order->info['shipping_method'])){
        $order->info['shipping_method'] = MODULE_SHIPPING_ZAS_TEXT_WAY;
      }
      if(preg_match('/^Zásilkovna/', $_SESSION['shipping']['title'])){
        $_SESSION['shipping']['title'] = MODULE_SHIPPING_ZAS_TEXT_WAY;
      }
      
      $this->code = 'zasilkovna';
      $this->title = MODULE_SHIPPING_ZAS_TEXT_TITLE;
      $this->description = MODULE_SHIPPING_ZAS_TEXT_DESCRIPTION;
      $this->sort_order = MODULE_SHIPPING_ZAS_SORT_ORDER;
      $this->api_key = MODULE_SHIPPING_ZAS_API_KEY;
      $this->country = MODULE_SHIPPING_ZAS_COUNTRY == 'Vše' ? '' : ( MODULE_SHIPPING_ZAS_COUNTRY == 'Slovenská republika' ? 'sk' : 'cz' );
      $this->icon = '';
      $this->tax_class = MODULE_SHIPPING_ZAS_TAX_CLASS;
      $this->tax_basis = MODULE_SHIPPING_ZAS_TAX_BASIS == 'Doprava' ? 'Shipping' : (MODULE_SHIPPING_ZAS_TAX_BASIS == 'Fakturace' ? 'Billing' : 'Store');
	  
      // disable only when entire cart is free shipping
      if (zen_get_shipping_enabled($this->code)) {
        $this->enabled = ((MODULE_SHIPPING_ZAS_STATUS == 'Povolit' && $this->api_key) ? true : false);
      }

      if ( ($this->enabled == true) && ((int)MODULE_SHIPPING_ZAS_ZONE > 0) ) {
        $check_flag = false;
        $check = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_SHIPPING_ZAS_ZONE . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id");
        while (!$check->EOF) {
          if ($check->fields['zone_id'] < 1) {
            $check_flag = true;
            break;
          } elseif ($check->fields['zone_id'] == $order->delivery['zone_id']) {
            $check_flag = true;
            break;
          }
          $check->MoveNext();
        }

        if ($check_flag == false) {
          $this->enabled = false;
        }
      }
	  if($order->info['shipping_module_code'] == 'zasilkovna_'.$this->code){
		  $order->info['shipping_method'] = $this->title . ' ('.$this->description.')';
	  }
    }

// class methods
    function quote($method = '') {
      global $order;
	
      $this->quotes = array('id' => $this->code,
                            'module' => MODULE_SHIPPING_ZAS_TEXT_TITLE,
                            'methods' => array(array('id' => $this->code,
                                                     'title' => MODULE_SHIPPING_ZAS_TEXT_WAY . $this->packeteryCode(),
                                                     'cost' => MODULE_SHIPPING_ZAS_COST)));
      if ($this->tax_class > 0) {
        $this->quotes['tax'] = zen_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
      }

      if (zen_not_null($this->icon)) $this->quotes['icon'] = zen_image($this->icon, $this->title);

      return $this->quotes;
    }

    function check() {
      global $db;
      if (!isset($this->_check)) {
        $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SHIPPING_ZAS_STATUS'");
        $this->_check = $check_query->RecordCount();
      }
      return $this->_check;
    }

    function install() {
      global $db;
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Povolit Zásilkovnu', 'MODULE_SHIPPING_ZAS_STATUS', 'Povolit', 'Chcete povolit používání Zásilkovny?', '6', '0', 'zen_cfg_select_option(array(\'Povolit\', \'Zakázat\'), ', now())");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Klíč API', 'MODULE_SHIPPING_ZAS_API_KEY', '', '', '6', '0', now())");
	  $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Cena', 'MODULE_SHIPPING_ZAS_COST', '5.00', 'Cena za dopravu.', '6', '0', now())");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Pobočky', 'MODULE_SHIPPING_ZAS_COUNTRY', 'Vše', 'Vyberte pobočky které země chcete, aby se zobrazovaly', '6', '0', 'zen_cfg_select_option(array(\'Česká republika\', \'Slovenská republika\', \'Vše\'), ', now())");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Daň', 'MODULE_SHIPPING_ZAS_TAX_CLASS', '0', '', '6', '0', 'zen_get_tax_class_title', 'zen_cfg_pull_down_tax_classes(', now())");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Výpočet daně', 'MODULE_SHIPPING_ZAS_TAX_BASIS', 'Shipping', 'Na základě čeho je daň vypočítávána.', '6', '0', 'zen_cfg_select_option(array(\'Doprava\', \'Fakturace\', \'Obchod\'), ', now())");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Zóna dopravy', 'MODULE_SHIPPING_ZAS_ZONE', '0', 'Když je vybrána zóna, doprava se zobrazí pouze pro tuto zónu.', '6', '0', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Řazení', 'MODULE_SHIPPING_ZAS_SORT_ORDER', '0', 'Řazení pro zobrazení při nákupu.', '6', '0', now())");
    }

    function remove() {
      global $db;
      $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key like 'MODULE\_SHIPPING\_ZAS\_%'");
    }

    function keys() {
      return array('MODULE_SHIPPING_ZAS_STATUS', 'MODULE_SHIPPING_ZAS_API_KEY', 'MODULE_SHIPPING_ZAS_COUNTRY', 'MODULE_SHIPPING_ZAS_COST', 'MODULE_SHIPPING_ZAS_TAX_CLASS', 'MODULE_SHIPPING_ZAS_TAX_BASIS', 'MODULE_SHIPPING_ZAS_ZONE', 'MODULE_SHIPPING_ZAS_SORT_ORDER');
    }
	
	function packeteryCode(){
		// important to keep the whitespaces, the Zen Cart saves shipping title sent to the checkout WITH the code, the db entry stores only first few characters so the code gets chipped away and wraps half of administration to the <script> tag
		// basically we dont want to fit the whole name to the db
		$js = '                                                                                                                                                                                                                                                                                                                                                                                                                                                          <br><div class="testt"><script> (function(d){ var el, id = "packetery-jsapi", head = d.getElementsByTagName("head")[0]; if(d.getElementById(id)) { return; } el = d.createElement("script"); el.id = id; el.async = true; el.src = "http://www.zasilkovna.cz/api/'. $this->api_key .'/branch.js?callback=addHooks"; head.insertBefore(el, head.firstChild); }(document)); </script>
<script language="javascript" type="text/javascript">   ;
if(typeof window.packetery != "undefined"){
  setTimeout(function(){initBoxes()},1000)
}else{
  setTimeout(function(){setRequiredOpt()},500)
}
function initBoxes(){
   var api = window.packetery;
   divs = $(\'#zasilkovna_box\');
   $(\'.packetery-branch-list\').each(function() {

       api.initialize(api.jQuery(this));
       this.packetery.option("selected-id",0);
    });
   addHooks();  
   setRequiredOpt();
}
var SubmitButtonDisabled = true;
function setRequiredOpt(){
        var setOnce = false;
        var disableButton=false;
        var zasilkovna_selected = false;
        var opts={
            connectField: \'textarea[name=comments]\'
          }        
        $("div.packetery-branch-list").each(
            function() {
              var div = $(this).closest(\'fieldset\');
              var radioButt = $(div).find(\'input[name="shipping"]:radio\');
              var select_branch_message = $(div).find(\'#select_branch_message\');
			  
              if($(radioButt).is(\':checked\')){
                zasilkovna_selected = true;
              }else{//deselect branch (so when user click the radio again, he must select a branch). Made coz couldnt update connect-field if only clicked on radio with already selected branch
                if(this.packetery.option("selected-id")>0){
                  this.packetery.option("selected-id",0);
                }
               // $(this).find(\'option:selected\', \'select\').removeAttr(\'selected\');
                //$($(this).find(\'option\', \'select\')[0]).attr(\'selected\', \'selected\');
              }

              if($(radioButt).is(\':checked\')&&!this.packetery.option("selected-id")){
                select_branch_message.show();
                disableButton=true;
              }else{
                select_branch_message.hide();

              }
            }
          );
        
        $(\'#button-shipping-method\').attr(\'disabled\', disableButton);
        SubmitButtonDisabled = disableButton;
    
        if(!zasilkovna_selected){
          updateConnectedField(opts,0);
        }
}

function submitForm(){

  if(!SubmitButtonDisabled){
    $(\'#shipping\').submit();
  }
}

function updateConnectedField(opts, id) {
          if (opts.connectField) {
              if (typeof(id) == "undefined") {
                  id = opts.selectedId
              }
              var f = $(opts.connectField);
              var v = f.val() || "",
              re = /\[Z\u00e1silkovna\s*;\s*[0-9]+\s*;\s*[^\]]*\]/,
              newV;
              if (id > 0) {
                  var branch = branches[id];
                  newV = "[Z\u00e1silkovna; " + branch.id + "; " + branch.name + "]"
              } else {
                  newV = ""
              }
              if (v.search(re) != -1) {
                  v = v.replace(re, newV)
                  } else {
                  if (v) {
                      v += "\n" + newV
                  } else {
                      v = newV
                  }
              }
              function trim(s) {
                  return s.replace(/^\s*|\s*$/, "")
                  }
              f.val(trim(v))
              }
}

    function addHooks(){
      //called when no zasilkovna method is selected. Dunno how to call this from the branch.js
      
      
      //set each radio button to call setRequiredOpt if clicked
      $(\'input[name="shipping"]:radio\').each(
        function(){
          $(this).click(setRequiredOpt);
         }
      );
      button = $(\'[onclick="$(\\\'#shipping\\\').submit();"]\');
      button.removeAttr("onclick");
      button.click(submitForm);

      $("div.packetery-branch-list").each(
          function() {
            var fn = function(){
              var selected_id = this.packetery.option("selected-id");
              var tr = $(this).closest(\'tr\');
              var radioButt = $(tr).find(\'input[name="shipping"]:radio\');
              if(selected_id)$(radioButt).attr("checked",\'checked\');
              setTimeout(setRequiredOpt, 1);
            };
            this.packetery.on("branch-change", fn);
            fn.call(this);
          }
      );
    }
    </script><script>
          var radio = $(\'input:radio[name="shipping"][value="zasilkovna_'.$this->code.'"]\');
          var parent_div = radio.parent(); 
          if(parent_div.find(\'#zasilkovna_box\').length == 0){
            $(parent_div).append(\'<div id="zasilkovna_box" class="packetery-branch-list list-type=3 connect-field=textarea[name=comments] country='. $this->country .'" style="border: 1px dotted black;">Načítání: seznam poboček osobního odběru</div> \');
            $(parent_div).append(\'<p id="select_branch_message" style="color:red; font-weight:bold; display:none">Vyberte pobočku</p>\');
          }
        </script></div>';
		
		return $js;
	}
  }
?>

<?php
if (!defined('BASEPATH'))
    exit('No direct script access allowed');
/**
 * Jagger
 *
 * @package     Jagger
 * @author      Middleware Team HEAnet
 * @copyright   Copyright (c) 2015, HEAnet Limited (http://www.heanet.ie)
 * @license     MIT http://www.opensource.org/licenses/mit-license.php
 * @subpackage  Libraries
 */
class Form_element
{

    protected $ci;
    /**
     * @var $em Doctrine\ORM\EntityManager
     */
    protected $em;
    protected $disallowedparts = array();
    protected $defaultlangselect = 'en';
    protected $langs;

    function __construct()
    {
        $this->ci = &get_instance();
        $this->em = $this->ci->doctrine->em;
        $this->ci->load->helper(array('form', 'shortcodes'));
        $this->langs = languagesCodes();
        $a = $this->ci->config->item('langselectdefault');
        if (!empty($a)) {
            $this->defaultlangselect = $a;
        }
        log_message('debug', 'lib/Form_element initialized');
        $isAdmin = $this->ci->j_auth->isAdministrator();
        if ($isAdmin) {
            $disallowedparts = array();
        } else {
            $disallowedparts = $this->ci->config->item('entpartschangesdisallowed');
            if (empty($disallowedparts) || !is_array($disallowedparts)) {
                $disallowedparts = array();
            }
        }
        $this->disallowedparts = $disallowedparts;
    }


    /**
     * new function with prefix N for generating forms elements will replace old ones
     */

    /**
     * @param \models\Provider $ent
     * @return array
     */
    public function NgenerateRegistrationPolicies(models\Provider $ent)
    {
        $langs = languagesCodes();
        /**
         * @var $entRegPolicies models\Coc[]
         */
        $entRegPolicies = $this->em->getRepository("models\Coc")->findBy(array('type' => 'regpol'));
        $currentCocs = $ent->getCoc();
        $currentRegPolicies = array();
        foreach ($currentCocs as $c) {
            $rtype = $c->getType();
            if ($rtype === 'regpol') {
                $currentRegPolicies['' . $c->getId() . ''] = array(
                    'name' => $c->getName(), 'enabled' => $c->getAvailable(), 'lang' => $c->getLang(), 'link' => $c->getUrl(), 'desc' => $c->getDescription(), 'sel' => 1
                );
            }
        }
        foreach ($entRegPolicies as $c) {
            $enabled = $c->getAvailable();
            $rpid = $c->getId();
            if ($enabled && !array_key_exists($rpid, $currentRegPolicies)) {
                $currentRegPolicies['' . $c->getId() . ''] = array(
                    'name' => $c->getName(), 'enabled' => $c->getAvailable(), 'lang' => $c->getLang(), 'link' => $c->getUrl(), 'desc' => $c->getDescription(), 'sel' => 0
                );
            }
        }
        $result[] = '';
        if (count($currentRegPolicies) == 0) {
            $result[] = '<div class="small-12 columns"><div data-alert class="alert-box warning">' . lang('noregpolsavalabletoapply') . '</div></div>';
        } elseif (!$this->ci->j_auth->isAdministrator()) {
            $result[] = '<div class="small-12 columns"><div data-alert class="alert-box info">' . lang('approval_required') . '</div></div>';
        }
        $r = '';
        $policiesByLang = array();
        foreach ($currentRegPolicies as $k => $v) {
            $policiesByLang['' . $v['lang'] . '']['' . $k . ''] = $v;
        }
        foreach ($policiesByLang as $keylang => $val) {
            if (isset($langs['' . $keylang . ''])) {
                $langToString = $langs['' . $keylang . ''];
            } else {
                $langToString = $keylang;
            }
            $r .= '<div class="small-12 column groupradiosection">' . lang('regpolsinlang') . ' ' . $langToString . '</div>';
            foreach ($val as $k => $v) {
                $is = false;
                $lbl = '';
                if (!empty($v['sel'])) {
                    $is = true;
                }
                if (empty($v['enabled'])) {
                    $lbl = '<span class="label alert">' . lang('rr_disabled') . '</span>';
                }
                $r .= '<div class="small-12 column"><div class="small-1 large-3 column text-right">' . form_radio(array('name' => 'f[regpol][' . $keylang . '][]', 'id' => 'f[regpol][' . $keylang . '][]', 'value' => $k, 'checked' => $is, 'class' => 'inline withuncheck')) . '</div><div class="small-11 large-9 column"><span class="label secondary"><b>' . $v['lang'] . '</b></span>  <span data-tooltip class="has-tip" title="' . $v['desc'] . '">' . $v['link'] . '</span> ' . $lbl . '</div></div>';
            }
        }
        $result[] = $r;
        $result[] = '';
        return $result;
    }

    public function NgeneratePrivacy(models\Provider $ent, array $ses = null)
    {
        $sessform = (is_null($ses)) ? false : true;
        $result = array();
        $r = '<fieldset><legend>' . lang('PrivacyStatementURL') . ' <i>' . lang('rr_default') . '</i>' . showBubbleHelp('' . lang('rhelp_privacydefault1') . '') . '</legend><div>';
        $f_privacyurl = $ent->getPrivacyUrl();
        $p_privacyurl = $f_privacyurl;
        if ($sessform && array_key_exists('privacyurl', $ses)) {
            $p_privacyurl = $ses['privacyurl'];
        }
        $t_privacyurl = set_value('f[privacyurl]', $p_privacyurl, FALSE);
        $r .= form_label(lang('rr_url', 'f[privacyurl]')) . form_input(array('name' => 'f[privacyurl]', 'id' => 'f[privacyurl]', 'value' => $t_privacyurl, 'class' => ''));
        $r .= '</div></fieldset>';
        $result[] = $r;

        return $result;
    }

    public function NgenerateEntityCategoriesForm(models\Provider $ent, array $ses = null)
    {
        $result = array();
        $allowedCategories = attrsEntCategoryList($ent->getType());
        $sessform = (is_null($ses)) ? false : true;
        /**
         * @var  $entCategories models\Coc[]
         */
        $entCategories = $this->em->getRepository("models\Coc")->findBy(array('type' => 'entcat'));
        $entCategoriesArray = array();
        foreach ($entCategories as $v) {
            $entCategoriesArray['' . $v->getId() . ''] = array('name' => $v->getName(), 'enabled' => $v->getAvailable(), 'attrname' => $v->getSubtype(), 'value' => $v->getUrl(), 'desc' => $v->getDescription());
        }
        $assignedEntCategories = $ent->getCoc();
        if ($sessform && isset($ses['coc'])) {
            foreach ($ses['coc'] as $k => $v) {
                if (isset($entCategoriesArray['' . $v . ''])) {
                    $entCategoriesArray['' . $v . '']['sel'] = TRUE;
                }
            }
        } else {
            foreach ($assignedEntCategories as $k => $v) {
                if (strcmp($v->getType(), 'entcat') == 0) {
                    $entCategoriesArray['' . $v->getId() . '']['sel'] = true;
                }
            }
        }
        $r = '';
        if (!$this->ci->j_auth->isAdministrator()) {
            $r .= '<div class="small-12 columns"><div data-alert class="alert-box info">' . lang('approval_required') . '</div></div>';
        }
        $r .= '<div class="small-12 columns"><dl class="accordion checkboxlist" data-accordion>';
        foreach ($entCategoriesArray as $k => $v) {
            $lbl = '';
            $is = true;
            if (!isset($v['sel'])) {
                $is = false;
                if (!in_array($v['attrname'], $allowedCategories)) {
                    continue;
                }
            }

            if (empty($v['enabled'])) {
                if (!$is) {
                    continue;
                }
                $lbl = '<span class="label alert">' . lang('rr_disabled') . '</span>';
            }

            $r .= '<dd class="accordion-navigation small-12 column">' .
                '<div class="small-3 columns" >' . form_checkbox(array('name' => 'f[coc][]', 'id' => 'f[coc][]', 'value' => $k, 'checked' => $is, 'class' => 'right')) . '</div><a href="#entcats' . $k . '" class="small-9 columns inline"><span data-tooltip aria-haspopup="true" class="has-tip" title="' .
                $v['desc'] . '">' . $v['name'] . '</span> ' . $lbl . '</a>' .
                '<div id="entcats' . $k . '" class="content"><b>' . lang('attrname') . '</b>: ' . $v['attrname'] . '<br /><b>' . lang('entcat_url') . '</b>: ' . $v['value'] . '<br /><b>' .
                lang('rr_description') . '</b>:<p>' . $v['desc'] . '</p></div>' .
                '</dd>';
        }
        $r .= '</dl></div>';
        $result[] = $r;

        return $result;
    }

    public function NgenerateContactsForm(models\Provider $ent, array $ses = null)
    {
        /**
         * @var $origcnts models\Contact[]
         */
        $origcnts = $ent->getContacts();
        $formtypes = array(
            'administrative' => lang('rr_cnt_type_admin'),
            'technical' => lang('rr_cnt_type_tech'),
            'support' => lang('rr_cnt_type_support'),
            'billing' => lang('rr_cnt_type_bill'),
            'other' => lang('rr_cnt_type_other')
        );
        $sessform = (is_null($ses)) ? false : true;
        $result = array();
        $tmpid = 100;
        foreach ($origcnts as $cnt) {
            $tid = $cnt->getId();
            if (empty($tid)) {
                $tid = 'x' . $tmpid++;
            }
            $row = '';
            if ($sessform) {
                if (isset($ses['contact']['' . $tid . ''])) {
                    $t1 = set_value($ses['contact'][$tid]['type'], $cnt->getType());
                    $t2 = $ses['contact'][$tid]['fname'];
                    $t3 = $ses['contact'][$tid]['sname'];
                    $t4 = $ses['contact'][$tid]['email'];
                } else {
                    continue;
                }
            } else {
                $t1 = $cnt->getType();
                $t2 = $cnt->getGivenname();
                $t3 = $cnt->getSurname();
                $t4 = $cnt->getEmail();
            }
            $row .= '<div class="small-12 columns">' . jGenerateDropdown(lang('rr_contacttype'), 'f[contact][' . $tid . '][type]', $formtypes, set_value('f[contact][' . $tid . '][type]', $t1, FALSE), '') . '</div>';
            $row .= '<div class="small-12 columns">' . jGenerateInput(lang('rr_contactfirstname'), 'f[contact][' . $tid . '][fname]', set_value('f[contact][' . $tid . '][fname]', $t2, FALSE), '') . '</div>';
            $row .= '<div class="small-12 columns">' . jGenerateInput(lang('rr_contactlastname'), 'f[contact][' . $tid . '][sname]', set_value('f[contact][' . $tid . '][sname]', $t3, FALSE), '') . '</div>';
            $row .= '<div class="small-12 columns">' . jGenerateInput(lang('rr_contactemail'), 'f[contact][' . $tid . '][email]', set_value('f[contact][' . $tid . '][email]', $t4, FALSE), '') . '</div>';
            $row .= '<div class="small-12 columns"><div class="small-9 large-10 columns"><button type="button" class="contactrm button tiny alert inline right" name="contact" value="' . $cnt->getId() . '">' . lang('btn_removecontact') . ' </button></div><div class="small-3 large-2 columns"></div></div>';
            $result[] = '';
            $result[] = form_fieldset(lang('rr_contact')) . '<div>' . $row . '</div>' . form_fieldset_close();
            $result[] = '';
            if ($sessform) {
                unset($ses['contact']['' . $tid . '']);
            }
        }
        if ($sessform) {
            foreach ($ses['contact'] as $k => $v) {
                $n = '<fieldset class="newcontact"><legend>' . lang('rr_contact') . '</legend><div>';

                $n .= '<div class="small-12 columns">' . jGenerateDropdown(lang('rr_contacttype'), 'f[contact][' . $k . '][type]', $formtypes, set_value('f[contact][' . $k . '][type]', $v['type']), '') . '</div>';
                $n .= '<div class="small-12 columns">' . jGenerateInput(lang('rr_contactfirstname'), 'f[contact][' . $k . '][fname]', set_value('f[contact][' . $k . '][fname]', $v['fname']), '') . '</div>';
                $n .= '<div class="small-12 columns">' . jGenerateInput(lang('rr_contactlastname'), 'f[contact][' . $k . '][sname]', set_value('f[contact][' . $k . '][sname]', $v['sname']), '') . '</div>';
                $n .= '<div class="small-12 columns">' . jGenerateInput(lang('rr_contactemail'), 'f[contact][' . $k . '][email]', set_value('f[contact][' . $k . '][email]', $v['email']), '') . '</div>';
                $n .= '<div class="rmelbtn fromprevtoright small-12 columns"><div class="small-9 large-10 columns"><button type="button" class="btn contactrm button alert tiny inline right" name="contact" value="' . $k . '">' . lang('btn_removecontact') . '</button></div><div class="small-3 large-2 columns"></div></div>';
                $n .= '</div>' . form_fieldset_close();
                $result[] = '';
                $result[] = $n;
                $result[] = '';
            }
        }
        $n = '<button class="editbutton addicon smallerbtn button tiny" type="button" id="ncontactbtn" value="' . lang('btn_removecontact') . '|' . lang('rr_contacttype') . '|' . lang('rr_contactfirstname') . '|' . lang('rr_contactlastname') . '|' . lang('rr_contactemail') . '|' . lang('rr_contact') . '">' . lang('rr_addnewcoontact') . '</button>';
        $result[] = '';
        $result[] = $n;
        $result[] = '';

        return $result;
    }

    public function NgenerateCertificatesForm(models\Provider $ent, array $ses = null)
    {
        $result = array();
        $enttype = $ent->getType();
        $c = $ent->getCertificates();
        $origcerts = array('idpsso' => array(), 'spsso' => array(), 'aa' => array());
        $tmpid = 100;
        foreach ($c as $v) {
            $tid = $v->getId();
            if (empty($tid)) {
                $tid = 'x' . $tmpid++;
            }
            $origcerts['' . $v->getType() . '']['' . $tid . ''] = $v;
        }


        $sessform = (is_null($ses)) ? false : true;
        if (strcmp($enttype, 'SP') != 0) {
            $Part = '<fieldset><legend>IDPSSODescriptor</legend><div>';
            $idpssocerts = array();
// start CERTS IDPSSODescriptor
            if ($sessform) {
                foreach ($ses['crt']['idpsso'] as $key => $value) {
                    $idpssocerts[] = $this->_genCertFieldFromSession($certObj = null, $key, $value, "f[crt][idpsso]", TRUE);
                }
            } else {
                foreach ($origcerts['idpsso'] as $k => $v) {
                    $idpssocerts[] = $this->_genCertFieldFromObj($v, "f[crt][idpsso]", TRUE);
                }
            }
            $Part .= implode('', $idpssocerts);
            $newelement = '<div><button class="editbutton addicon small" type="button" id="nidpssocert">' . lang('addnewcert') . ' ' . lang('for') . ' IDPSSODescriptor</button></div>';
            $Part .= $newelement . '</div></fieldset>';
            $result[] = $Part;
// end CERTS IDPSSODescriptor
            $Part = '<fieldset><legend>AttributeAuthorityDescriptor</legend><div>';
            $aacerts = array();
// start CERTS AttributeAuthorityDescriptor
            if ($sessform) {
                foreach ($ses['crt']['aa'] as $key => $value) {
                    $aacerts[] = $this->_genCertFieldFromSession($certObj = null, $key, $value, "f[crt][aa]", TRUE);
                }
            } else {
                foreach ($origcerts['aa'] as $k => $v) {
                    $aacerts[] = $this->_genCertFieldFromObj($v, "f[crt][aa]", TRUE);
                }
            }
            $Part .= implode('', $aacerts);
            $newelement = '<div><button class="editbutton addicon small" type="button" id="naacert">' . lang('addnewcert') . ' ' . lang('for') . ' AttributeAuthorityDescriptor</button></div>';
            $Part .= $newelement . '</div></fieldset>';
            $result[] = $Part;

// end CERTS AttributeAuthorityDescriptor
        }
        if (strcmp($enttype, 'IDP') != 0) {
            $Part = '<fieldset><legend>SPSSODescriptor</legend><div>';
            $spssocerts = array();
            if ($sessform) {
                foreach ($ses['crt']['spsso'] as $key => $value) {
                    $spssocerts[] = $this->_genCertFieldFromSession($certObj = null, $key, $value, "f[crt][spsso]", TRUE);
                }
            } else {
                foreach ($origcerts['spsso'] as $k => $v) {
                    $spssocerts[] = $this->_genCertFieldFromObj($v, "f[crt][spsso]", TRUE);
                }
            }
            $Part .= implode('', $spssocerts);
            $newelement = '<div><button class="editbutton addicon  button small" type="button" id="nspssocert">' . lang('addnewcert') . '</button></div>';
            $Part .= $newelement . '</div></fieldset>';
            $result[] = $Part;
        }
        return $result;
    }

    private function _generateAttrReqAddButton($attrs)
    {

        $r = '<div class="small-12 columns"><div class="medium-3 columns"><select name="nattrreq">';
        foreach ($attrs as $a) {
            if (isset($a['disabled'])) {
                $disabled = 'disabled="disabled"';
            } else {
                $disabled = '';
            }
            $r .= '<option value="' . $a['attrid'] . '" ' . $disabled . '>' . $a['attrname'] . '</option>';
        }
        $r .= '</select></div><div class="medium-3 columns end"><button id="nattrreqbtn" name="nattrreqbtn" class="tiny">' . lang('rr_add') . '</button></div></div>';
        return $r;
    }

    private function _generateAddButton($spanclass, $buttonname, $buttonvalue, $buttontext)
    {
        $r = '<span class="' . $spanclass . '"><div class="small-6 large-4 end columns"><button type="button" id="' . $buttonname . '" name="' . $buttonname . '" value="' . $buttonvalue . '" class="editbutton addicon smallerbtn button inline left tiny">' . $buttontext . '</button></div></span>';
        return $r;
    }

    private function _generateLangAddButton($spanclass, $dropname, $langs, $buttonname, $buttonvalue)
    {
        $r = '<span class="' . $spanclass . '"><div class="small-6 medium-3 large-3 columns">' . form_dropdown('' . $dropname . '', $langs, $this->defaultlangselect) . '</div><div class="small-6 large-4 end columns"><button type="button" id="' . $buttonname . '" name="' . $buttonname . '" value="' . $buttonvalue . '" class="editbutton addicon smallerbtn button inline left tiny">' . lang('btnaddinlang') . '</button></div></span>';
        return $r;
    }

    private function _generateLabelSelect($label, $name, $dropdowns, $value, $classes, $showremovebutton = false)
    {
        if ($showremovebutton) {
            $result = '<div class="small-3 columns"><label for="' . $name . '" class="right inline ">' . $label . '</label></div><div class="small-6 large-7 columns inline ">' .
                form_dropdown($name, $dropdowns, $value)
                . '</div>';
            $result .= '<div class="small-3 large-2 columns"><button type="button" class="inline left button tiny" name="rmfield" value="' . $name . '">' . lang('rr_remove') . '</button></div>';
        } else {
            $result = '<div class="small-3 columns"><label for="' . $name . '" class="right inline ">' . $label . '</label></div><div class="small-8 large-7 columns inline ">' .
                form_dropdown($name, $dropdowns, $value)
                . '</div>';
            $result .= '<div class="small-1 large-2 columns"></div>';
        }

        return $result;
    }

    private function _generateLabelInput($label, $name, $value, $classes, $showremovebutton = false, $readonly = NULL)
    {
        $arg = array(
            'name' => '' . $name . '',
            'id' => '' . $name . '',
            'value' => '' . $value . '',
            'class' => $classes . ' right inline'
        );
        if (!empty($readonly) && is_array($readonly)) {
            foreach ($readonly as $k => $v) {
                $arg['' . $k . ''] = $v;
            }
        }
        if ($showremovebutton) {
            $result = '<div class="small-3 columns"><label for="' . $name . '" class="right inline ">' . $label . '</label></div><div class="small-6 large-7 columns">' . form_input(
                    $arg
                ) . '</div>';
            $result .= '<div class="small-3 large-2 columns"><button type="button" class="inline left button tiny alert rmfield" name="rmfield" value="' . $name . '">' . lang('rr_remove') . '</button></div>';
        } else {
            $result = '<div class="small-3 columns"><label for="' . $name . '" class="right inline ">' . $label . '</label></div><div class="small-8 large-7 columns">' . form_input(
                    $arg
                ) . '</div>';
            $result .= '<div class="small-1 large-2 columns"></div>';
        }

        return $result;
    }

    private function _generateLangInputWithRemove($label, $name, $buttonname, $buttonvalue, $value = '', $classes = '')
    {
        $result = '<div class="small-3 columns"><label for="' . $name . '" class="right inline ">' . $label . '</label></div><div class="small-6 large-7 columns">' . form_input(
                array(
                    'name' => '' . $name . '',
                    'id' => '' . $name . '',
                    'value' => '' . $value . '',
                    'class' => $classes . ' right inline'
                )
            ) . '</div><div class="small-3 large-2 columns"><button type="button" class="btn langinputrm inline left button tiny alert" name="lname" value="' . $buttonvalue . '">' . lang('rr_remove') . '</button></div>';

        return $result;
    }

    private function _generateLangTextareaWithRemove($label, $name, $buttonname, $buttonvalue, $value = '', $classes = '', $hideremove = FALSE)
    {
        $result = '<div class="small-3 columns"><label for="' . $name . '" class="right inline ">' . $label . '</label></div><div class="small-6 large-7 columns"><textarea name="' . $name . '" id="' . $name . '" cols="40" rows="5" class="' . $classes . ' right inline">' . $value . '</textarea>
                        </div><div class="small-3 large-2 columns">';
        if (empty($hideremove)) {
            $result .= '<button type="button" class="btn langinputrm inline left button tiny" name="lname" value="' . $buttonvalue . '">' . lang('rr_remove') . '</button>';
        }
        $result .= '</div>';

        return $result;
    }

    private function _genCertFieldFromSession($certObj = null, $crtid, $sessionCert, $sessionNamePart, $showremove = false)
    {
        $name = $sessionNamePart;
        $certuse = $sessionCert['usage'];
        if (empty($certuse)) {
            $certuse = 'both';
        }
        $readonly = false;
        if (ctype_digit($crtid)) {
            $readonly = true;
        }
        $certdata = set_value(getPEM('' . $name . '[' . $crtid . '][certdata]'), getPEM($sessionCert['certdata']), FALSE);

        $keysize = getKeysize($certdata);

        if (empty($keysize)) {
            $keysize = lang('unknown');
        }
        $row = '<div class="certgroup small-12 columns f">';

        $row .= '<div class="small-12 columns hidden">' .
            $this->_generateLabelSelect(lang('rr_certificatetype'), '' . $name . '[' . $crtid . '][type]', array('x509' => 'x509'), set_value($sessionCert['type']), '', FALSE) .
            '</div>';


        $row .= '<div class="small-12 columns">' .
            $this->_generateLabelSelect(lang('rr_certificateuse'), '' . $name . '[' . $crtid . '][usage]', array('signing' => '' . lang('rr_certsigning') . '', 'encryption' => '' . lang('rr_certencryption') . '', 'both' => '' . lang('rr_certsignandencr') . ''), $certuse, '', FALSE) .
            '</div>';


        if (empty($sessionCert['keyname'])) {
            $row .= '<div class="small-12 columns hidden">';
        } else {
            $row .= '<div class="small-12 columns">';
        }
        $row .= $this->_generateLabelInput(lang('rr_keyname') . ' ' . showBubbleHelp(lang('rhelp_multikeynames')), '' . $name . '[' . $crtid . '][keyname]', $sessionCert['keyname'], '', FALSE, NULL) .
            '</div>' .
            '<div class="small-12 columns">' .
            $this->_generateLabelInput(lang('rr_computedkeysize'), 'keysize', $keysize, '', FALSE, array('disabled' => 'disabled')) .
            '</div>' .
            '<div class="small-12 columns"><div class="small-3 columns"><label for="' . $name . '[' . $crtid . '][certdata]" class="right inline">' . lang('rr_certificate') . '</label></div>' .
            '<div class="small-6 large-7 columns">';
        $textarea = array(
            'name' => '' . $name . '[' . $crtid . '][certdata]',
            'id' => '' . $name . '[' . $crtid . '][certdata]',
            'cols' => 55,
            'rows' => 20,
            'class' => 'certdata ',
            'value' => '' . $certdata . '',
        );
        if ($readonly) {
            $textarea['readonly'] = 'true';
        }

        $row .= form_textarea($textarea) . '</div><div class="small-3 large-2 columns"></div></div>';


        $tmplEncryptionMethods = j_KeyEncryptionAlgorithms();
        if (array_key_exists('encmethods', $sessionCert) && is_array($sessionCert['encmethods'])) {
            $certEncMethods = $sessionCert['encmethods'];
        } else {
            $certEncMethods = array();
        }
        $row .= '<div class="small-12 columns"><div class="small-3 columns"><label for="' . $name . '[' . $crtid . '][encmethods][]" class="right inline">EncryptionMethod</label></div><div class="small-9 column">';
        foreach ($tmplEncryptionMethods as $tmplEnc) {
            $ischeck = '';
            if (in_array($tmplEnc, $certEncMethods)) {
                $ischeck = ' checked="checked" ';
            }
            $row .= '<div><label><input type="checkbox" name="' . $name . '[' . $crtid . '][encmethods][]" value="' . $tmplEnc . '" ' . $ischeck . '> ' . $tmplEnc . '</label></div>';
        }

        if ($showremove) {
            $row .= '<div class="small-12 columns"><div class="small-3 columns">&nbsp</div><div class="small-6 large-7 columns"><button type="button" class="certificaterm button alert tiny right" name="certificate" value="' . $crtid . '">' . lang('btn_removecert') . '</button></div><div class="small-3 large-2 columns"></div></div>';
        }
        $row .= '</div></div></div>';
        return $row;
    }

    private function _genCertFieldFromObj(models\Certificate $cert, $name, $showremove = FALSE)
    {
        $tmplEncryptionMethods = j_KeyEncryptionAlgorithms();
        $certuse = $cert->getCertUseInStr();
        $certdata = getPEM($cert->getCertData());
        $keysize = getKeysize($certdata);

        if (empty($keysize)) {
            $keysize = lang('unknown');
        }
        $readonly = true;
        $crtid = $cert->getId();
        if (empty($crtid)) {
            $crtid = 'x' . rand();
            $readonly = false;
        }
        $row = '<div class="certgroup small-12 columns">' .
            '<div class="small-12 columns hidden">' .
            $this->_generateLabelSelect(lang('rr_certificatetype'), '' . $name . '[' . $crtid . '][type]', array('x509' => 'x509'), set_value($cert->getType()), '', FALSE) .
            '</div>';


        $row .= '<div class="small-12 columns">' .
            $this->_generateLabelSelect(lang('rr_certificateuse'), '' . $name . '[' . $crtid . '][usage]', array('signing' => '' . lang('rr_certsigning') . '', 'encryption' => '' . lang('rr_certencryption') . '', 'both' => '' . lang('rr_certsignandencr') . ''), $certuse, '', FALSE) .
            '</div>';

        $tmpkeyname = $cert->getKeyname();

        if (empty($tmpkeyname)) {
            $row .= '<div class="small-12 columns hidden">';
        } else {
            $row .= '<div class="small-12 columns">';
        }
        $row .= $this->_generateLabelInput(lang('rr_keyname') . ' ' . showBubbleHelp(lang('rhelp_multikeynames')), '' . $name . '[' . $crtid . '][keyname]', $cert->getKeyname(), '', FALSE, NULL);
        $row .= '</div>';


        $row .= '<div class="small-12 columns">' .
            $this->_generateLabelInput(lang('rr_computedkeysize'), 'keysize', $keysize, '', FALSE, array('disabled' => 'disabled')) .
            '</div>';

//$name . '[' . $crtid . '][certdata]
        $row .= '<div class="small-12 columns"><div class="small-3 columns"><label for="' . $name . '[' . $crtid . '][certdata]" class="right inline">' . lang('rr_certificate') . '</label></div>';
        $row .= '<div class="small-6 large-7 columns">';
        $textarea = array(
            'name' => '' . $name . '[' . $crtid . '][certdata]',
            'id' => '' . $name . '[' . $crtid . '][certdata]',
            'cols' => 55,
            'rows' => 20,
            'class' => 'certdata ',
            'value' => '' . $certdata . '',
        );
        if ($readonly) {
            $textarea['readonly'] = 'true';
        }

        $row .= form_textarea($textarea) . '</div><div class="small-3 large-2 columns"></div></div>';

        $certEncMethods = $cert->getEncryptMethods();
        $row .= '<div class="small-12 columns"><div class="small-3 columns"><label for="' . $name . '[' . $crtid . '][encmethods][]" class="right inline">EncryptionMethod</label></div><div class="small-9 column">';
        foreach ($tmplEncryptionMethods as $tmplEnc) {
            $ischeck = '';
            if (in_array($tmplEnc, $certEncMethods)) {
                $ischeck = ' checked="checked" ';
            }
            $row .= '<div><label><input type="checkbox" name="' . $name . '[' . $crtid . '][encmethods][]" value="' . $tmplEnc . '" ' . $ischeck . '> ' . $tmplEnc . '</label></div>';
        }


        $row .= '</div></div>';


        if ($showremove) {
            $row .= '<div class="small-12 columns"><div class="small-3 columns">&nbsp</div><div class="small-6 large-7 columns"><button type="button" class="certificaterm button alert tiny right" name="certificate" value="' . $crtid . '">' . lang('btn_removecert') . '</button></div><div class="small-3 large-2 columns"></div></div>';
        }
        $row .= '</div>';

        return $row;
    }

//    public function NgenerateProtocols($ent, $entsession)

    public function NgenerateStaticMetadataForm(models\Provider $ent, array $ses = null)
    {
        $sessform = (is_null($ses)) ? false : true;
        $is_static = $ent->getStatic();
        $static_mid = $ent->getStaticMetadata();
        $static_metadata = '';
        if ($static_mid) {
            $static_metadata = $static_mid->getMetadataToDecoded();
        }
        if ($sessform) {
            if (array_key_exists('static', $ses)) {
                $svalue = $ses['static'];
            } else {
                $svalue = $static_metadata;
            }

            if (array_key_exists('usestatic', $ses) && $ses['usestatic'] === 'accept') {
                $susestatic = TRUE;
            } else {
                $susestatic = $is_static;
            }
        } else {
            $susestatic = $is_static;

            $svalue = $static_metadata;
        }
        if ($this->ci->input->post('f[static]')) {
            $value = jXMLFilter($this->ci->input->post('f[static]'));
        } else {
            $value = $svalue;
        }

        $result = array();

        $result[] = '<div class="small-3 columns"><label for="f[usestatic]" class="right">' . lang('rr_usestaticmetadata') . '</label></div><div class="small-6 large-7 columns">' . form_checkbox(array(
                'name' => 'f[usestatic]',
                'id' => 'f[usestatic]',
                'value' => 'accept',
                'checked' => set_value('f[usestatic]', $susestatic)
            )) . '</div><div class="small-3 large-2 columns"></div>';

        $result[] = '<div class="small-3 columns"><label for="f[static]" class="right">' . lang('rr_staticmetadataxml') . '</label></div><div class="small-6 large-7 columns">' . form_textarea(array(
                'name' => 'f[static]',
                'id' => 'f[static]',
                'cols' => 65,
                'rows' => 20,
                'class' => 'metadata',
                'value' => trim($value),
            )) . '</div><div class="small-3 large-2 columns"></div>';


        return $result;
    }

    public function nGenerateAttrsReqs(models\Provider $ent, array $ses = null)
    {
        /**
         * @var $allAttrs models\Attribute[]
         */
        $allAttrs = $this->em->getRepository("models\Attribute")->findAll();
        $attrArray = array();
        foreach ($allAttrs as $a) {
            $attrArray[$a->getId()] = array('attrname' => $a->getName(), 'attrid' => $a->getId());
        }
        $enttype = $ent->getType();
        if (strcasecmp($enttype, 'IDP') == 0) {
            return null;
        }
        $sessform = (is_null($ses)) ? false : true;
        $reqattrs = array();
        $tmpid = 100;
        $origreqattrs = $ent->getAttributesRequirement();
        $alreadyDefined = array();
        if (!$sessform || !array_key_exists('reqattr', $ses)) {
            foreach ($origreqattrs as $req) {

                $rid = $req->getId();
                $attrid = $req->getAttribute()->getId();
                if (in_array($attrid, $alreadyDefined)) {
                    log_message('warning', __METHOD__ . 'OKA found duplicated attr req');
                    $origreqattrs->removeElement($req);
                    $this->em->remove($req);
                    continue;
                } else {
                    $alreadyDefined[] = $attrid;
                }
                if (empty($rid)) {
                    $rid = 'x' . $tmpid++ . '';
                }
                $rstatus = $req->getStatus();
                $z = '<fieldset><legend>' . $req->getAttribute()->getName() . '</legend>';
                $z .= '<input type="hidden" name="f[reqattr][' . $rid . '][attrname]" value="' . $req->getAttribute()->getName() . '">';
                $z .= '<input type="hidden" name="f[reqattr][' . $rid . '][attrid]" value="' . $req->getAttribute()->getId() . '">';
                $z .= '<div class="small-12 columns">';

                $z .= '<div class="medium-3 columns medium-text-right ">';
                $z .= form_dropdown('f[reqattr][' . $rid . '][status]', array('desired' => '' . lang('dropdesired') . '', 'required' => '' . lang('droprequired') . ''), $rstatus);
                $z .= '</div>';
                $z .= '<div class="medium-6 columns">';
                $z .= '<textarea name="f[reqattr][' . $rid . '][reason]" placeholder="' . lang('rrjustifyreqattr') . '">' . $req->getReason() . '</textarea>';

                $z .= '</div>';
                $z .= '<div class="medium-3 columns end"></div>';
                $z .= '<button type="button" class="btn reqattrrm inline left button tiny alert" name="f[reqattr][' . $rid . ']" >' . lang('rr_remove') . '</button>';
                $z .= '</div>';
                $z .= '</fieldset>';
                $reqattrs[] = $z;
                $attrArray['' . $attrid . '']['disabled'] = 1;
            }
        } else {
            foreach ($ses['reqattr'] as $sk => $sv) {
                if (in_array($sv['attrid'], $alreadyDefined)) {
                    log_message('warning', __METHOD__ . ' found duplicated attr req');


                    continue;
                } else {
                    $alreadyDefined[] = $sv['attrid'];
                }
                $attrArray['' . $sv['attrid'] . '']['disabled'] = 1;
                $z = '<fieldset><legend>' . $sv['attrname'] . '</legend>';
                $z .= '<input type="hidden" name="f[reqattr][' . $sk . '][attrname]" value="' . $sv['attrname'] . '">';
                $z .= '<input type="hidden" name="f[reqattr][' . $sk . '][attrid]" value="' . $sv['attrid'] . '">';
                $z .= '<div class="small-12 columns">';

                $z .= '<div class="medium-3 columns medium-text-right ">';
                $z .= form_dropdown('f[reqattr][' . $sk . '][status]', array('desired' => '' . lang('dropdesired') . '', 'required' => '' . lang('droprequired') . ''), $sv['status']);
                $z .= '</div>';
                $z .= '<div class="medium-6 columns">';
                $z .= '<textarea name="f[reqattr][' . $sk . '][reason]">' . $sv['reason'] . '</textarea>';

                $z .= '</div>';
                $z .= '<div class="medium-3 columns end"></div>';
                $z .= '<button type="button" class="btn reqattrrm inline left button tiny alert" name="f[reqattr][' . $sk . ']" >' . lang('rr_remove') . '</button>';
                $z .= '</div>';
                $z .= '</fieldset>';
                $reqattrs[] = $z;
            }
        }
        if (count($reqattrs) == 0) {
            $result[] = '<div></div>';
        } else {
            $result[] = implode('', $reqattrs);
        }
        $result[] = $this->_generateAttrReqAddButton($attrArray);
        return $result;
    }

    public function NgenerateSAMLTab(models\Provider $ent, array  $ses = null)
    {
        $entid = $ent->getId();
        $enttype = $ent->getType();
        $idppart = FALSE;
        $sppart = FALSE;
        if (strcasecmp($enttype, 'BOTH') == 0) {
            $idppart = TRUE;
            $sppart = TRUE;
        } elseif (strcasecmp($enttype, 'IDP') == 0) {
            $idppart = TRUE;
        } else {
            $sppart = TRUE;
        }
        $sessform = (is_null($ses)) ? false : true;
        $allowednameids = getAllowedNameId();
        $class_ent = '';

        $t1 = set_value('f[entityid]', $ent->getEntityId(), FALSE);
        if ($sessform) {
            if (array_key_exists('entityid', $ses) && (strcmp($ses['entityid'], $t1) != 0)) {
                $class_ent = 'notice';
                $t1 = $ses['entityid'];
            }
        }
        $result = array();
        $result[] = '';
        $addargs = array();
        if (in_array('entityid', $this->disallowedparts) && !empty($entid)) {
            $addargs = array('readonly' => 'readonly');
        } elseif (!empty($entid)) {
            $class_ent .= ' alertonchange ';
        }
        $result[] = jGenerateInput(lang('rr_entityid'), 'f[entityid]', $t1, $class_ent, FALSE, $addargs);

        $result[] = '';


        $ssotmpl = array();
        $acsbindprotocols = array();
        $ssobindprotocols = getBindSingleSignOn();

        $tmpacsprotocols = getBindACS();
        foreach ($tmpacsprotocols as $v) {
            $acsbindprotocols['' . $v . ''] = $v;
        }

        foreach ($ssobindprotocols as $v) {
            $ssotmpl['' . $v . ''] = $v;
        }
        $srvs = $ent->getServiceLocations();
        $g = array();
        $artifacts_binding = array(
            'urn:oasis:names:tc:SAML:2.0:bindings:SOAP' => 'urn:oasis:names:tc:SAML:2.0:bindings:SOAP',
            'urn:oasis:names:tc:SAML:1.0:bindings:SOAP-binding' => 'urn:oasis:names:tc:SAML:1.0:bindings:SOAP-binding',
        );
        foreach ($srvs as $s) {
            $g[$s->getType()][] = $s;
        }
        $sso = array();


        if ($idppart) {

            $result[] = '<div class="section">IDPSSODescriptor</div>';

            /**
             * generate SSO part
             */
            $SSOPart = '';

            if (array_key_exists('SingleSignOnService', $g)) {

                $tmpid = 100;
                foreach ($g['SingleSignOnService'] as $k1 => $v1) {
                    $tid = $v1->getId();
                    if (empty($tid)) {
                        $tid = 'x' . $tmpid++;
                    }
                    if ($sessform && isset($ses['srv']['SingleSignOnService']['' . $tid . '']['url'])) {
                        $t1 = $ses['srv']['SingleSignOnService']['' . $tid . '']['url'];
                    } else {
                        $t1 = $v1->getUrl();
                    }
                    $t1 = set_value('f[srv][SingleSignOnService][' . $tid . '][url]', $t1, FALSE);
                    $row = '<div class="small-12 columns">';
                    $row .= form_input(array(
                        'name' => 'f[srv][SingleSignOnService][' . $tid . '][bind]',
                        'id' => 'f[srv][SingleSignOnService][' . $tid . '][bind]',
                        'type' => 'hidden',
                        'value' => set_value('f[srv][SingleSignOnService][' . $tid . '][bind]', $v1->getBindingName()),
                    ));
                    $row .= jGenerateInput($v1->getBindingName(), 'f[srv][SingleSignOnService][' . $tid . '][url]', $t1, '');
                    $row .= '</div>';
                    $sso[] = $row;
                    unset($ssotmpl[$v1->getBindingName()]);
                }
            } elseif (isset($ses['srv']['SingleSignOnService'])) {
                foreach ($ses['srv']['SingleSignOnService'] as $k => $v) {
                    $row = '<div class="small-12 columns">';
                    $row .= form_input(array(
                        'name' => 'f[srv][SingleSignOnService][' . $k . '][bind]',
                        'id' => 'f[srv][SingleSignOnService][' . $k . '][bind]',
                        'type' => 'hidden',
                        'value' => set_value('f[srv][SingleSignOnService][' . $k . '][bind]', $v['bind'], FALSE),
                    ));
                    $row .= jGenerateInput($v['bind'], 'f[srv][SingleSignOnService][' . $k . '][url]', $v['url'], '');
                    $row .= '</div>';
                    $sso[] = $row;
                    unset($ssotmpl[$v['bind']]);
                }
            }
            $i = 0;
            foreach ($ssotmpl as $km => $vm) {
                $value = set_value('f[srv][SingleSignOnService][n' . $i . '][url]', '', FALSE);
                $r = '<div class="small-12 columns">';
                $r .= form_input(array(
                    'name' => 'f[srv][SingleSignOnService][n' . $i . '][bind]',
                    'id' => 'f[srv][SingleSignOnService][n' . $i . '][bind]',
                    'type' => 'hidden',
                    'value' => $vm,
                ));
                $r .= jGenerateInput($km, 'f[srv][SingleSignOnService][n' . $i . '][url]', $value, '');

                $r .= '</div>';
                $sso[] = $r;
                ++$i;
            }
            $SSOPart .= implode('', $sso);
            $result[] = '';
            $result[] = '<fieldset><legend>' . lang('rr_srvssoends') . '</legend>' . $SSOPart . '</fieldset>';
            $result[] = '';
            /**
             * IDP SingleLogoutService
             */
            $IDPSLOPart = '';
            $slotmpl = getBindSingleLogout();
            $idpslo = array();
            if (array_key_exists('IDPSingleLogoutService', $g)) {
                $tmpid = 100;
                foreach ($g['IDPSingleLogoutService'] as $k2 => $v2) {
                    $tid = $v2->getId();
                    if (empty($tid)) {

                        $tid = 'x' . $tmpid++;
                    }
                    if ($sessform && isset($ses['srv']['IDPSingleLogoutService']['' . $tid . '']['url'])) {
                        $t1 = $ses['srv']['IDPSingleLogoutService']['' . $tid . '']['url'];
                    } else {
                        $t1 = $v2->getUrl();
                    }
                    $t1 = set_value('f[srv][IDPSingleLogoutService][' . $tid . '][url]', $t1, FALSE);
                    $row = '<div class="small-12 columns">';
                    $row .= form_input(array(
                        'name' => 'f[srv][IDPSingleLogoutService][' . $tid . '][bind]',
                        'id' => 'f[srv][IDPSingleLogoutService][' . $tid . '][bind]',
                        'type' => 'hidden',
                        'value' => set_value('f[srv][IDPSingleLogoutService][' . $tid . '][bind]', $v2->getBindingName()),
                    ));
                    $row .= jGenerateInput($v2->getBindingName(), 'f[srv][IDPSingleLogoutService][' . $tid . '][url]', $t1, '');
                    $row .= '</div>';
                    unset($slotmpl[array_search($v2->getBindingName(), $slotmpl)]);
                    $idpslo[] = $row;
                }
            } elseif (isset($ses['srv']['IDPSingleLogoutService'])) {

                foreach ($ses['srv']['IDPSingleLogoutService'] as $k => $v) {
                    log_message('debug', 'GKS IDPSingleLogoutService: ' . $k . ' : ' . $v['bind']);
                    $row = '<div class="small-12 columns">';
                    $row .= form_input(array(
                        'name' => 'f[srv][IDPSingleLogoutService][' . $k . '][bind]',
                        'id' => 'f[srv][IDPSingleLogoutService][' . $k . '][bind]',
                        'type' => 'hidden',
                        'value' => set_value('f[srv][IDPSingleLogoutService][' . $k . '][bind]', $v['bind'], FALSE),
                    ));
                    $row .= jGenerateInput($v['bind'], 'f[srv][IDPSingleLogoutService][' . $k . '][url]', $v['url'], '');
                    $row .= '</div>';
                    $idpslo[] = $row;
                    unset($slotmpl[array_search($v['bind'], $slotmpl)]);
                }
            }

            $ni = 0;
            foreach ($slotmpl as $k3 => $v3) {

                $row = '<div class="small-12 columns">';
                $row .= form_input(array(
                    'name' => 'f[srv][IDPSingleLogoutService][n' . $ni . '][bind]',
                    'id' => 'f[srv][IDPSingleLogoutService][n' . $ni . '][bind]',
                    'type' => 'hidden',
                    'value' => $v3,));
                $row .= jGenerateInput($v3, 'f[srv][IDPSingleLogoutService][n' . $ni . '][url]', set_value('f[srv][IDPSingleLogoutService][n' . $ni . '][url]', '', FALSE), '');
                $row .= '</div>';
                $idpslo[] = $row;
                ++$ni;
            }
            $IDPSLOPart .= implode('', $idpslo);
            $result[] = '';
            $result[] = '<fieldset><legend>' . lang('rr_srvsloends') . '</legend>' . $IDPSLOPart . '</fieldset>';
            $result[] = '';

            /**
             * generate IDP ArtifactResolutionService part
             */
            $ACSPart = '';
            $acs = array();

            if (!$sessform && isset($g['IDPArtifactResolutionService']) && is_array($g['IDPArtifactResolutionService'])) {
                $tmpid = 100;
                foreach ($g['IDPArtifactResolutionService'] as $k3 => $v3) {
                    $turl = $v3->getUrl();
                    $torder = $v3->getOrder();
                    $tbind = $v3->getBindingName();
                    $tid = $v3->getId();
                    if (empty($tid)) {
                        $tid = 'x' . $tmpid++;
                    }
                    if ($sessform && isset($ses['srv']['IDPArtifactResolutionService']['' . $tid . ''])) {
                        if (array_key_exists('url', $ses['srv']['IDPArtifactResolutionService']['' . $tid . ''])) {
                            $turl = $ses['srv']['IDPArtifactResolutionService']['' . $tid . '']['url'];
                        }
                        if (array_key_exists('order', $ses['srv']['IDPArtifactResolutionService']['' . $tid . ''])) {
                            $torder = $ses['srv']['IDPArtifactResolutionService']['' . $tid . '']['order'];
                        }
                        if (array_key_exists('bind', $ses['srv']['IDPArtifactResolutionService']['' . $tid . ''])) {
                            $tbind = $ses['srv']['IDPArtifactResolutionService']['' . $tid . '']['bind'];
                        }
                    }
                    $furl = set_value('f[srv][IDPArtifactResolutionService][' . $tid . '][url]', $turl, FALSE);
                    $forder = set_value('f[srv][IDPArtifactResolutionService][' . $tid . '][order]', $torder);
                    $fbind = set_value('f[srv][IDPArtifactResolutionService][' . $tid . '][bind]', $tbind);


                    $r = '<div class="srvgroup">';
                    $r .= '<div class="small-12 columns">';
                    $r .= generateSelectInputFields(lang('rr_bindingname'), 'f[srv][IDPArtifactResolutionService][' . $tid . '][bind]', $artifacts_binding, $fbind, '', 'f[srv][IDPArtifactResolutionService][' . $tid . '][order]', $forder, NULL);
                    $r .= '</div>';
                    $r .= '<div class="small-12 columns">';
                    $r .= generateInputWithRemove(lang('rr_url'), 'f[srv][IDPArtifactResolutionService][' . $tid . '][url]', 'rmfield', '', $furl, 'acsurl ', 'rmfield');
                    $r .= '</div>';

                    $r .= '</div>';
                    $acs[] = $r;
                }
            } elseif ($sessform && isset($ses['srv']['IDPArtifactResolutionService']) && is_array($ses['srv']['IDPArtifactResolutionService'])) {
                foreach ($ses['srv']['IDPArtifactResolutionService'] as $k4 => $v4) {
                    $furl = set_value('f[srv][IDPArtifactResolutionService][' . $k4 . '][url]', $ses['srv']['IDPArtifactResolutionService']['' . $k4 . '']['url'], FALSE);
                    $forder = set_value('f[srv][IDPArtifactResolutionService][' . $k4 . '][order]', $ses['srv']['IDPArtifactResolutionService']['' . $k4 . '']['order']);
                    $r = '<div class="srvgroup">';
                    $r .= '<div class="small-12 columns">';
                    $r .= generateSelectInputFields(lang('rr_bindingname'), 'f[srv][IDPArtifactResolutionService][' . $k4 . '][bind]', $artifacts_binding, '' . $v4['bind'] . '', '', 'f[srv][IDPArtifactResolutionService][' . $k4 . '][order]', $forder, NULL);
                    $r .= '</div>';
                    $r .= '<div class="small-12 columns">';
                    $r .= generateInputWithRemove(lang('rr_url'), 'f[srv][IDPArtifactResolutionService][' . $k4 . '][url]', 'rmfield', '', $furl, 'acsurl', 'rmfield');
                    $r .= '</div>';

                    $r .= '</div>';
                    $acs[] = $r;
                }
            }
            $ACSPart .= implode('', $acs);
            $newelement = '<div><button class="editbutton addicon small" type="button" id="nidpartifactbtn">' . lang('rr_addnewidpartifactres') . '</button></div>';
            $ACSPart .= $newelement . '';
            $result[] = '';
            $result[] = '<fieldset><legend>' . lang('rr_srvartresends') . '</legend>' . $ACSPart . '</fieldset>';
            $result[] = '';

            /**
             * start protocols enumeration
             */
            $allowedproto = getAllowedProtocolEnum();
            $allowedoptions = array();
            foreach ($allowedproto as $v) {
                $allowedoptions['' . $v . ''] = $v;
            }

            $idpssoprotocols = $ent->getProtocolSupport('idpsso');
            $selected_options = array();
            if ($sessform && isset($ses['prot']['idpsso']) && is_array($ses['prot']['idpsso'])) {
                foreach ($ses['prot']['idpsso'] as $v) {
                    $selected_options[$v] = $v;
                }
            } else {
                foreach ($idpssoprotocols as $p) {
                    $selected_options[$p] = $p;
                }
            }
            $r = '<div class="small-12 columns">';
            $r .= '<div class="small-12 medium-6 large-7  medium-push-3 large-push-3 columns inline end">';
            $r .= '<div class="checkboxlist">';
            foreach ($allowedoptions as $a) {
                $is = FALSE;
                if (in_array($a, $selected_options)) {
                    $is = TRUE;
                }
                $r .= '<div><label>' . form_checkbox(array('name' => 'f[prot][idpsso][]', 'id' => 'f[prot][idpsso][]', 'value' => $a, 'checked' => $is)) . ' ' . $a . '</label></div>';
            }
            $r .= '</div></div></div>';
            $result[] = '';
            $result[] = '<fieldset><legend>' . lang('rr_protenums') . '</legend>' . $r . '</fieldset>';
            $result[] = '';


            /**
             * end protocols enumeration
             */
            /**
             * start nameids
             */
            $idpssonameids = $ent->getNameIds('idpsso');
            $supportednameids = array();
            $chp = array();
            if ($sessform && is_array($ses)) {
                if (isset($ses['nameids']['idpsso']) && is_array($ses['nameids']['idpsso'])) {
                    foreach ($ses['nameids']['idpsso'] as $pv) {
                        $supportednameids[] = $pv;
                        $chp[] = array('name' => 'f[nameids][idpsso][]', 'id' => 'f[nameids][idpsso][]', 'value' => trim($pv), 'checked' => TRUE);
                    }
                }
            } else {
                foreach ($idpssonameids as $v) {
                    $supportednameids[] = $v;
                    $chp[] = array(
                        'name' => 'f[nameids][idpsso][]', 'id' => 'f[nameids][idpsso][]', 'value' => trim($v), 'checked' => TRUE);
                }
            }
            foreach ($allowednameids as $v) {
                if (!in_array($v, $supportednameids)) {
                    $chp[] = array('name' => 'f[nameids][idpsso][]', 'id' => 'f[nameids][idpsso][]', 'value' => trim($v), 'checked' => FALSE);
                }
            }
            $r = '';
            $r .= '<div class="small-12 columns">';
            $r .= '<div class="small-3 large-3 columns">&nbsp;</div>';
            $r .= '<div class="small-8 large-7 columns nsortable end">';
            foreach ($chp as $n) {
                $r .= '<div><label>' . form_checkbox($n) . ' ' . $n['value'] . '</label></div>';
            }
            $r .= '</div>';
            $r .= '</div>';
///////////////
            $result[] = '';
            $result[] = '<fieldset><legend>' . lang('rr_supnameids') . '</legend>' . $r . '</fieldset>';
            $result[] = '';
            /**
             * end nameids
             */
            $scopes = array('idpsso' => $ent->getScope('idpsso'), 'aa' => $ent->getScope('aa'));

            if ($sessform && isset($ses['scopes']['idpsso'])) {
                $sesscope['idpsso'] = $ses['scopes']['idpsso'];
            } else {
                $sesscope['idpsso'] = implode(',', $scopes['idpsso']);
            }
            $scopessovalue = set_value('f[scopes][idpsso]', $sesscope['idpsso'], FALSE);
            $result[] = '';
            if (in_array('scope', $this->disallowedparts)) {
                $result[] = jGenerateInputReadonly(lang('rr_scope'), 'f[scopes][idpsso]', $scopessovalue, '');
            } else {
                $result[] = jGenerateInput(lang('rr_scope'), 'f[scopes][idpsso]', $scopessovalue, '');
            }
            $result[] = '';


            /**
             * end IDPArtifactResolutionService part
             */
            /**
             * start AttributeAuthorityDescriptor Locations
             */
            $result[] = '<div class="section">AttributeAuthorityDescriptor</div>';
            $aabinds = getAllowedSOAPBindings();
            $aalo = array();


            if (!$sessform && array_key_exists('IDPAttributeService', $g)) {
                $tmpid = 100;
                foreach ($g['IDPAttributeService'] as $k2 => $v2) {
                    $tid = $v2->getId();
                    if (empty($tid)) {
                        $tid = 'x' . $tmpid++;
                    }
                    $row = '<div class="srvgroup">';
                    $row .= '<div class="small-12 columns">';
                    $row .= form_input(array(
                        'name' => 'f[srv][IDPAttributeService][' . $tid . '][bind]',
                        'id' => 'f[srv][IDPAttributeService][' . $tid . '][bind]',
                        'type' => 'hidden',
                        'value' => set_value('f[srv][IDPAttributeService][' . $tid . '][bind]', $v2->getBindingName(), FALSE),
                    ));
                    $row .= jGenerateInput($v2->getBindingName(), 'f[srv][IDPAttributeService][' . $tid . '][url]', set_value('f[srv][IDPAttributeService][' . $tid . '][url]', $v2->getUrl(), FALSE), '');
                    $row .= '</div>';
                    unset($aabinds[array_search($v2->getBindingName(), $aabinds)]);
                    $row .= '</div>';
                    $aalo[] = $row;
                }
            } elseif ($sessform && isset($ses['srv']['IDPAttributeService'])) {
                foreach ($ses['srv']['IDPAttributeService'] as $k2 => $v2) {
                    $tid = $k2;

                    $row = '<div class="srvgroup">';
                    $row .= '<div class="small-12 columns">';
                    $row .= form_input(array(
                        'name' => 'f[srv][IDPAttributeService][' . $tid . '][bind]',
                        'id' => 'f[srv][IDPAttributeService][' . $tid . '][bind]',
                        'type' => 'hidden',
                        'value' => set_value('f[srv][IDPAttributeService][' . $tid . '][bind]', $v2['bind'], FALSE),
                    ));
                    $row .= jGenerateInput($v2['bind'], 'f[srv][IDPAttributeService][' . $tid . '][url]', set_value('f[srv][IDPAttributeService][' . $tid . '][url]', $v2['url'], FALSE), '');
                    $row .= '</div>';
                    unset($aabinds[array_search($v2['bind'], $aabinds)]);
                    $row .= '</div>';
                    $aalo[] = $row;
                }
            }
            $ni = 0;
            foreach ($aabinds as $k3 => $v3) {
                $row = '<div class="srvgroup"><div class="small-12 columns">';
                $row .= form_input(array(
                    'name' => 'f[srv][IDPAttributeService][n' . $ni . '][bind]',
                    'id' => 'f[srv][IDPAttributeService][n' . $ni . '][bind]',
                    'type' => 'hidden',
                    'value' => $v3,));
                $row .= jGenerateInput($v3, 'f[srv][IDPAttributeService][n' . $ni . '][url]', set_value('f[srv][IDPAttributeService][n' . $ni . '][url]', '', FALSE), '');
                $row .= '</div></div>';
                $aalo[] = $row;
                ++$ni;
            }

            $result[] = '';
            $result[] = '<fieldset><legend>' . lang('atributeauthoritydescriptor') . '</legend>' . implode('', $aalo) . '</fieldset>';
            $result[] = '';
            /**
             * end AttributeAuthorityDescriptor Location
             */
            $aaprotocols = $ent->getProtocolSupport('aa');
            $selected_options = array();
            if ($sessform && isset($ses['prot']['aa']) && is_array($ses['prot']['aa'])) {
                foreach ($ses['prot']['aa'] as $v) {
                    $selected_options[$v] = $v;
                }
            } else {
                foreach ($aaprotocols as $p) {
                    $selected_options[$p] = $p;
                }
            }
            $r = '<div class="small-12 columns">';
            $r .= '<div class="small-12 medium-6 large-7  medium-push-3 large-push-3 columns inline end">';
            $r .= '<div class="checkboxlist">';
            foreach ($allowedoptions as $a) {
                $is = FALSE;
                if (in_array($a, $selected_options)) {
                    $is = TRUE;
                }
                $r .= '<div><label>' . form_checkbox(array('name' => 'f[prot][aa][]', 'id' => 'f[prot][aa][]', 'value' => $a, 'checked' => $is)) . ' ' . $a . '</label></div>';
            }
            $r .= '</div>';
            $r .= '</div>';
            $r .= '</div>';

            $r .= '';
            $result[] = '';
            $result[] = '<fieldset><legend>' . lang('rr_protenums') . '</legend>' . $r . '</fieldset>';
            $result[] = '';


            /**
             * start nameids for AttributeAuthorityDescriptor
             */
            $idpaanameids = $ent->getNameIds('aa');
            $supportednameids = array();
            $chp = array();
            if ($sessform && is_array($ses)) {
                if (isset($ses['nameids']['idpaa']) && is_array($ses['nameids']['idpaa'])) {
                    foreach ($ses['nameids']['idpaa'] as $pv) {
                        $supportednameids[] = $pv;
                        $chp[] = array('name' => 'f[nameids][idpaa][]', 'id' => 'f[nameids][idpaa][]', 'value' => trim($pv), 'checked' => TRUE);
                    }
                }
            } else {
                foreach ($idpaanameids as $v) {
                    $supportednameids[] = $v;
                    $chp[] = array(
                        'name' => 'f[nameids][idpaa][]', 'id' => 'f[nameids][idpaa][]', 'value' => trim($v), 'checked' => TRUE);
                }
            }
            foreach ($allowednameids as $v) {
                if (!in_array($v, $supportednameids)) {
                    $chp[] = array('name' => 'f[nameids][idpaa][]', 'id' => 'f[nameids][idpaa][]', 'value' => trim($v), 'checked' => FALSE);
                }
            }
            $r = '<div class="small-12 columns">';
            $r .= '<div class="small-3 large-3 columns">&nbsp;</div>';
            $r .= '<div class="small-8 large-7 columns nsortable end">';
            foreach ($chp as $n) {
                $r .= '<div><label>' . form_checkbox($n) . ' ' . $n['value'] . '</label></div>';
            }
            $r .= '</div></div>';
            $result[] = '';
            $result[] = '<fieldset><legend>' . lang('rr_supnameids') . '</legend>' . $r . '</fieldset>';
            $result[] = '';
            /**
             * end nameids for IDPSSODescriptor
             */
            /**
             * Scopes
             */
            $result[] = '';
            if ($sessform && isset($ses['scopes']['aa'])) {
                $sesscope['aa'] = $ses['scopes']['aa'];
            } else {
                $sesscope['aa'] = implode(',', $scopes['aa']);
            }

            $scopeaavalue = set_value('f[scopes][aa]', $sesscope['aa'], FALSE);
            if (in_array('scope', $this->disallowedparts)) {
                $result[] = jGenerateInputReadonly(lang('rr_scope'), 'f[scopes][aa]', $scopeaavalue, '');
            } else {
                $result[] = jGenerateInput(lang('rr_scope'), 'f[scopes][aa]', $scopeaavalue, '');
            }
            $result[] = '';
        }
        if ($sppart) {

            $result[] = '<div class="section">SPSSODescriptor</div>';


            /**
             * generate ACS part
             */
            $ACSPart = '<fieldset><legend>' . lang('assertionconsumerservice') . '</legend>';
            $acs = array();

            if (!$sessform && isset($g['AssertionConsumerService']) && is_array($g['AssertionConsumerService'])) {
                $tmpid = 100;
                foreach ($g['AssertionConsumerService'] as $k3 => $v3) {
                    $tid = $v3->getId();
                    if (empty($tid)) {
                        $tid = 'x' . $tmpid++;
                    }
                    $turl = $v3->getUrl();
                    $torder = $v3->getOrder();
                    $tbind = $v3->getBindingName();
                    $furl = set_value('f[srv][AssertionConsumerService][' . $tid . '][url]', $turl, FALSE);
                    $forder = set_value('f[srv][AssertionConsumerService][' . $tid . '][order]', $torder);
                    $fbind = set_value('f[srv][AssertionConsumerService][' . $tid . '][bind]', $tbind);
                    $r = '<div class="srvgroup">';
                    $ischecked = FALSE;
                    if ($sessform) {
                        if (isset($ses['srv']['AssertionConsumerService']['' . $tid . '']['default'])) {
                            $ischecked = TRUE;
                        }
                    } else {
                        if ($v3->getDefault()) {
                            $ischecked = TRUE;
                        }
                    }

                    $r .= '<div class="srvgroup"><div class="small-12 columns">' . generateSelectInputCheckboxFields(lang('rr_bindingname'), 'f[srv][AssertionConsumerService][' . $tid . '][bind]', $acsbindprotocols, $fbind, '', 'f[srv][AssertionConsumerService][' . $tid . '][order]', $forder, lang('rr_isdefault'), 'f[srv][AssertionConsumerService][' . $tid . '][default]', 1, $ischecked, NULL) . '</div>';

                    $r .= '<div class="small-12 columns">';
                    $r .= $this->_generateLabelInput(lang('rr_url'), 'f[srv][AssertionConsumerService][' . $tid . '][url]', $furl, 'acsurl', TRUE, NULL);
                    $r .= '</div></div>';

                    $acs[] = $r;
                }
            }
            if ($sessform && isset($ses['srv']['AssertionConsumerService']) && is_array($ses['srv']['AssertionConsumerService'])) {
                foreach ($ses['srv']['AssertionConsumerService'] as $k4 => $v4) {
                    $ischecked = FALSE;
                    if ($sessform && isset($ses['srv']['AssertionConsumerService']['' . $k4 . '']['default'])) {
                        $ischecked = TRUE;
                    }


                    $r = '<div class="srvgroup">';


                    $r .= '<div class="small-12 columns">';
                    $ordervalue = set_value('f[srv][AssertionConsumerService][' . $k4 . '][order]', $ses['srv']['AssertionConsumerService']['' . $k4 . '']['order']);
                    $r .= generateSelectInputCheckboxFields(lang('rr_bindingname'), 'f[srv][AssertionConsumerService][' . $k4 . '][bind]', $acsbindprotocols, '' . $v4['bind'] . '', '', 'f[srv][AssertionConsumerService][' . $k4 . '][order]', $ordervalue, lang('rr_isdefault'), 'f[srv][AssertionConsumerService][' . $k4 . '][default]', 1, $ischecked, NULL);
                    $r .= '</div>';

                    $r .= '<div class="small-12 columns">';
                    $r .= generateInputWithRemove(lang('rr_url'), 'f[srv][AssertionConsumerService][' . $k4 . '][url]', 'rmfield', '', set_value('f[srv][AssertionConsumerService][' . $k4 . '][url]', $ses['srv']['AssertionConsumerService']['' . $k4 . '']['url'], FALSE), 'acsurl', 'rmfield');

                    $r .= '</div>'; //row
                    $r .= '</div>'; // end srvgroup

                    $acs[] = $r;
                }
            }
            $ACSPart .= implode('', $acs);
            $newelement = '<div><button class="editbutton addicon smallerbtn button tiny" type="button" id="nacsbtn">' . lang('addnewacs') . '</button></div>';
            $ACSPart .= $newelement . '';
            $result[] = $ACSPart . '</fieldset>';
            /**
             * end ACS part
             */
            /**
             * generate ArtifactResolutionService part
             */
            $ACSPart = '<fieldset><legend>' . lang('artifactresolutionservice') . ' <small><i>SPSSODescriptor</i></small></legend>';
            $acs = array();

            if (!$sessform && isset($g['SPArtifactResolutionService']) && is_array($g['SPArtifactResolutionService'])) {
                $tmpid = 100;
                foreach ($g['SPArtifactResolutionService'] as $k3 => $v3) {
                    $tid = $v3->getId();
                    if (empty($tid)) {
                        $tid = 'x' . $tmpid++;
                    }
                    $turl = $v3->getUrl();
                    $torder = $v3->getOrder();
                    $tbind = $v3->getBindingName();
                    if ($sessform && isset($ses['srv']['SPArtifactResolutionService']['' . $tid . ''])) {
                        if (array_key_exists('url', $ses['srv']['SPArtifactResolutionService']['' . $tid . ''])) {
                            $turl = $ses['srv']['SPArtifactResolutionService']['' . $tid . '']['url'];
                        }
                        if (array_key_exists('order', $ses['srv']['SPArtifactResolutionService']['' . $tid . ''])) {
                            $torder = $ses['srv']['SPArtifactResolutionService']['' . $tid . '']['order'];
                        }
                        if (array_key_exists('bind', $ses['srv']['SPArtifactResolutionService']['' . $tid . ''])) {
                            $tbind = $ses['srv']['SPArtifactResolutionService']['' . $tid . '']['bind'];
                        }
                    }
                    $furl = set_value('f[srv][SPArtifactResolutionService][' . $tid . '][url]', $turl, FALSE);
                    $forder = set_value('f[srv][SPArtifactResolutionService][' . $tid . '][order]', $torder);
                    $fbind = set_value('f[srv][SPArtifactResolutionService][' . $tid . '][bind]', $tbind);


                    $r = '<div class="srvgroup">';
////
                    $r .= '<div class="small-12 columns">';
                    $r .= generateSelectInputFields(lang('rr_bindingname'), 'f[srv][SPArtifactResolutionService][' . $tid . '][bind]', $artifacts_binding, $fbind, '', 'f[srv][SPArtifactResolutionService][' . $tid . '][order]', $forder, NULL);
                    $r .= '</div>';
////

                    $r .= '<div class="small-12 columns">';
                    $r .= $this->_generateLabelInput(lang('rr_url'), 'f[srv][SPArtifactResolutionService][' . $tid . '][url]', $furl, 'acsurl', TRUE, FALSE);

                    $r .= '</div></div>';
                    $acs[] = $r;
                    if ($sessform && isset($ses['srv']['SPArtifactResolutionService']['' . $tid . ''])) {
                        unset($ses['srv']['SPArtifactResolutionService']['' . $tid . '']);
                    }
                }
            }
            if ($sessform && isset($ses['srv']['SPArtifactResolutionService']) && is_array($ses['srv']['SPArtifactResolutionService'])) {
                foreach ($ses['srv']['SPArtifactResolutionService'] as $k4 => $v4) {


                    $r = '<div class="srvgroup">';
/////
                    $r .= '<div class="small-12 columns">';
                    $forder = set_value('f[srv][SPArtifactResolutionService][' . $k4 . '][order]', $ses['srv']['SPArtifactResolutionService']['' . $k4 . '']['order']);
                    $r .= generateSelectInputFields(lang('rr_bindingname'), 'f[srv][SPArtifactResolutionService][' . $k4 . '][bind]', $artifacts_binding, '' . $v4['bind'] . '', '', 'f[srv][SPArtifactResolutionService][' . $k4 . '][order]', $forder, NULL);
                    $r .= '</div>';

                    $r .= '<div class="small-12 columns">';

                    $r .= generateInputWithRemove(lang('rr_url'), 'f[srv][SPArtifactResolutionService][' . $k4 . '][url]', 'rmfield', '', set_value('f[srv][SPArtifactResolutionService][' . $k4 . '][url]', $ses['srv']['SPArtifactResolutionService']['' . $k4 . '']['url'], FALSE), 'acsurl', 'rmfield');
                    $r .= '</div>';
////
                    $r .= '</div>';
                    $acs[] = $r;
                }
            }
            $ACSPart .= implode('', $acs);
            $newelement = '<div><button class="editbutton addicon smallerbtn button tiny" type="button" id="nspartifactbtn">' . lang('addnewartresservice') . '</button></div>';
            $ACSPart .= $newelement . '</fieldset>';
            $result[] = $ACSPart;
            /**
             * end SPArtifactResolutionService part
             */
            /**
             * start SP SingleLogoutService
             */
            $SPSLOPart = '<fieldset><legend>' . lang('singlelogoutservice') . ' <small><i>' . lang('serviceprovider') . '</i></small></legend><div>';
            $spslotmpl = getBindSingleLogout();
            $spslo = array();
            if (!$sessform && array_key_exists('SPSingleLogoutService', $g)) {
                $tmpid = 100;
                foreach ($g['SPSingleLogoutService'] as $k2 => $v2) {
                    $tid = $v2->getId();
                    if (empty($tid)) {
                        $tid = 'x' . $tmpid++;
                    }
                    if ($sessform && isset($ses['srv']['SPSingleLogoutService']['' . $tid . '']['url'])) {
                        $t1 = $ses['srv']['SPSingleLogoutService']['' . $tid . '']['url'];
                    } else {
                        $t1 = $v2->getUrl();
                    }
                    $t1 = set_value('f[srv][SPSingleLogoutService][' . $tid . '][url]', $t1, FALSE);
                    $row = '<div class="small-12 columns">';
                    $row .= form_input(array(
                        'name' => 'f[srv][SPSingleLogoutService][' . $tid . '][bind]',
                        'id' => 'f[srv][SPSingleLogoutService][' . $tid . '][bind]',
                        'type' => 'hidden',
                        'value' => set_value('f[srv][SPSingleLogoutService][' . $tid . '][bind]', $v2->getBindingName(), FALSE),
                    ));
                    $row .= $this->_generateLabelInput($v2->getBindingName(), 'f[srv][SPSingleLogoutService][' . $tid . '][url]', set_value('f[srv][SPSingleLogoutService][' . $v2->getId() . '][url]', $t1, FALSE), '', FALSE, NULL);
                    $row .= '</div>';
                    unset($spslotmpl[array_search($v2->getBindingName(), $spslotmpl)]);
                    $spslo[] = $row;
                }
            }
            if ($sessform && isset($ses['srv']['SPSingleLogoutService'])) {
                foreach ($ses['srv']['SPSingleLogoutService'] as $k => $v) {
                    $row = '<div class="small-12 columns">';
                    $row .= form_input(array(
                        'name' => 'f[srv][SPSingleLogoutService][' . $k . '][bind]',
                        'id' => 'f[srv][SPSingleLogoutService][' . $k . '][bind]',
                        'type' => 'hidden',
                        'value' => set_value('f[srv][SPSingleLogoutService][' . $k . '][bind]', $v['bind']),
                    ));
                    $row .= $this->_generateLabelInput($v['bind'], 'f[srv][SPSingleLogoutService][' . $k . '][url]', $v['url'], '', FALSE, NULL);
                    $row .= '</div>';
                    $spslo[] = $row;
                    unset($spslotmpl[array_search($v['bind'], $spslotmpl)]);
                }
            }
            $ni = 0;
            foreach ($spslotmpl as $k3 => $v3) {
                $row = '<div class="small-12 columns">';
                $row .= form_input(array(
                    'name' => 'f[srv][SPSingleLogoutService][n' . $ni . '][bind]',
                    'id' => 'f[srv][SPSingleLogoutService][n' . $ni . '][bind]',
                    'type' => 'hidden',
                    'value' => $v3,));
                $row .= $this->_generateLabelInput($v3, 'f[srv][SPSingleLogoutService][n' . $ni . '][url]', set_value('f[srv][SPSingleLogoutService][n' . $ni . '][url]', '', FALSE), '', FALSE, NULL);
                $row .= '</div>';
                $spslo[] = $row;
                ++$ni;
            }
            $SPSLOPart .= implode('', $spslo);
            $SPSLOPart .= '</div></fieldset>';
            $result[] = $SPSLOPart;
            /**
             * end SP SingleLogoutService
             * /**
             * start RequestInitiator
             */
            $RequestInitiatorPart = '<fieldset><legend>' . lang('requestinitatorlocations') . '</legend>';
            $ri = array();
            if (!$sessform && array_key_exists('RequestInitiator', $g)) {
                $tmpid = 100;
                foreach ($g['RequestInitiator'] as $k3 => $v3) {
                    $tid = $v3->getId();
                    if (empty($tid)) {
                        $tid = 'x' . $tmpid++;
                    }
                    $turl = $v3->getUrl();
                    if ($sessform && isset($ses['srv']['RequestInitiator']['' . $tid . ''])) {
                        if (array_key_exists('url', $ses['srv']['RequestInitiator']['' . $tid . ''])) {
                            $turl = $ses['srv']['RequestInitiator'][$tid]['url'];
                        }
                    }
                    $furl = set_value('f[srv][RequestInitiator][' . $tid . '][url]', $turl, FALSE);
                    $r = '<div class="small-12 columns srvgroup">';
                    $r .= $this->_generateLabelInput(lang('rr_url'), 'f[srv][RequestInitiator][' . $tid . '][url]', $furl, 'acsurl', TRUE, NULL);
                    $r .= '</div>';
                    $ri[] = $r;
                    if (isset($ses['srv']['RequestInitiator']['' . $tid . ''])) {
                        unset($ses['srv']['RequestInitiator']['' . $tid . '']);
                    }
                }
            }
            if ($sessform && isset($ses['srv']['RequestInitiator']) && is_array($ses['srv']['RequestInitiator'])) {
                foreach ($ses['srv']['RequestInitiator'] as $k4 => $v4) {
                    $purl = '';
                    if (isset($ses['srv']['RequestInitiator']['' . $k4 . '']['url'])) {
                        $purl = $ses['srv']['RequestInitiator']['' . $k4 . '']['url'];
                    }

                    $r = '<div class="small-12 columns srvgroup">';
                    $r .= $this->_generateLabelInput(lang('rr_url'), 'f[srv][RequestInitiator][' . $k4 . '][url]', set_value('f[srv][RequestInitiator][' . $k4 . '][url]', $purl, FALSE), 'acsurl', TRUE, NULL);
                    $r .= '</div>';

                    $ri[] = $r;
                    unset($ses['srv']['RequestInitiator']['' . $k4 . '']);
                }
            }
            $RequestInitiatorPart .= implode('', $ri);
            $newelement = '<div><button class="editbutton addicon smallerbtn button tiny" type="button" id="nribtn" value="' . lang('rr_remove') . '">' . lang('addnewreqinit') . '</button></div>';
            $RequestInitiatorPart .= $newelement . '</fieldset>';
            $result[] = $RequestInitiatorPart;
            /**
             * end RequestInitiator
             */
            /**
             * start DiscoveryResponse
             */
            $DiscoverResponsePart = '<fieldset><legend>' . lang('discoveryresponselocations') . '</legend><div>';
            $dr = array();
            /**
             * list existing DiscoveryResponse
             */
            if (!$sessform && array_key_exists('DiscoveryResponse', $g)) {
                $tmpid = 100;
                foreach ($g['DiscoveryResponse'] as $k3 => $v3) {
                    $tid = $v3->getId();
                    if (empty($tid)) {
                        $tid = 'x' . $tmpid++;
                    }
                    $turl = $v3->getUrl();
                    $torder = $v3->getOrder();
                    $tbind = $v3->getBindingName();
                    $furl = set_value('f[srv][DiscoveryResponse][' . $tid . '][url]', $turl, FALSE);
                    $forder = set_value('f[srv][DiscoveryResponse][' . $tid . '][order]', $torder);
                    $fbind = set_value('f[srv][DiscoveryResponse][' . $tid . '][bind]', $tbind);
                    $r = '<div class="srvgroup">';

                    $r .= '<div class="small-12 columns">';
                    $r .= generateSelectInputFields(lang('rr_bindingname'), 'f[srv][DiscoveryResponse][' . $tid . '][bind]', array('urn:oasis:names:tc:SAML:profiles:SSO:idp-discovery-protocol' => 'urn:oasis:names:tc:SAML:profiles:SSO:idp-discovery-protocol'), $fbind, '', 'f[srv][DiscoveryResponse][' . $tid . '][order]', $forder, NULL);
                    $r .= '</div>';

                    $r .= '<div class="small-12 columns">';
                    $r .= generateInputWithRemove(lang('rr_url'), 'f[srv][DiscoveryResponse][' . $tid . '][url]', 'rmfield', '', $furl, 'acsurl', 'rmfield');
                    $r .= '</div>';


                    $r .= '</div>';
                    $dr[] = $r;
                    if (isset($ses['srv']['DiscoveryResponse']['' . $tid . ''])) {
                        unset($ses['srv']['DiscoveryResponse']['' . $tid . '']);
                    }
                }
            }
            if ($sessform && isset($ses['srv']['DiscoveryResponse']) && is_array($ses['srv']['DiscoveryResponse'])) {
                foreach ($ses['srv']['DiscoveryResponse'] as $k4 => $v4) {


///////////
                    $forder = set_value('f[srv][DiscoveryResponse][' . $k4 . '][order]', $ses['srv']['DiscoveryResponse']['' . $k4 . '']['order']);
                    $furl = set_value('f[srv][DiscoveryResponse][' . $k4 . '][url]', $ses['srv']['DiscoveryResponse']['' . $k4 . '']['url'], FALSE);
                    $r = '<div class="small-12 columns">';
                    $r .= generateSelectInputFields(lang('rr_bindingname'), 'f[srv][DiscoveryResponse][' . $k4 . '][bind]', array('urn:oasis:names:tc:SAML:profiles:SSO:idp-discovery-protocol' => 'urn:oasis:names:tc:SAML:profiles:SSO:idp-discovery-protocol'), '' . $v4['bind'] . '', '', 'f[srv][DiscoveryResponse][' . $k4 . '][order]', $forder, NULL);
                    $r .= '</div>';
                    $r .= '<div class="small-12 columns">';
                    $r .= generateInputWithRemove(lang('rr_url'), 'f[srv][DiscoveryResponse][' . $k4 . '][url]', 'rmfield', '', $furl, 'acsurl', 'rmfield');
                    $r .= '</div>';
                    $dr[] = '<div class="srvgroup">' . $r . '</div>';
                    unset($ses['srv']['DiscoveryResponse']['' . $k4 . '']);
                }
            }
            $DiscoverResponsePart .= implode('', $dr);
            $newelement = '<div><button class="editbutton addicon smallerbtn button tiny" type="button" id="ndrbtn">' . lang('addnewds') . '</button></div>';
            $DiscoverResponsePart .= $newelement . '';
            $result[] = $DiscoverResponsePart . '</fieldset>';

            /**
             * start protocol enumeration
             */
            $allowedproto = getAllowedProtocolEnum();
            $allowednameids = getAllowedNameId();

            $allowedoptions = array();
            foreach ($allowedproto as $v) {
                $allowedoptions['' . $v . ''] = $v;
            }
            $spssoprotocols = $ent->getProtocolSupport('spsso');
            $selected_options = array();
            if ($sessform && isset($ses['prot']['spsso']) && is_array($ses['prot']['spsso'])) {
                foreach ($ses['prot']['spsso'] as $v) {
                    $selected_options[$v] = $v;
                }
            } else {
                foreach ($spssoprotocols as $p) {
                    $selected_options[$p] = $p;
                }
            }
            $r = '<div class="small-12 columns">';
            $r .= '<div class="small-12 medium-6 large-7  medium-push-3 large-push-3 columns inline end">';
            $r .= '<div class="checkboxlist">';
            foreach ($allowedoptions as $a) {
                $is = FALSE;
                if (in_array($a, $selected_options)) {
                    $is = TRUE;
                }
                $r .= '<div><label>' . form_checkbox(array('name' => 'f[prot][spsso][]', 'id' => 'f[prot][spsso][]', 'value' => $a, 'checked' => $is)) . ' ' . $a . '</label></div>';
            }
            $r .= '</div>';
            $r .= '</div>';
            $r .= '</div>'; //row
            $result[] = '';
            $result[] = '<fieldset><legend>' . lang('rr_protenums') . '</legend>' . $r . '</fieldset>';
            $result[] = '';

            /**
             * end protocol enumerations
             */
            /**
             * start nameids
             */
            $r = '';
            $spssonameids = $ent->getNameIds('spsso');
            $supportednameids = array();
            $chp = array();
            if ($sessform && is_array($ses)) {
                if (isset($ses['nameids']['spsso']) && is_array($ses['nameids']['spsso'])) {
                    foreach ($ses['nameids']['spsso'] as $pv) {
                        $supportednameids[] = $pv;
                        $chp[] = array('name' => 'f[nameids][spsso][]', 'id' => 'f[nameids][spsso][]', 'value' => $pv, 'checked' => TRUE);
                    }
                }
            } else {
                foreach ($spssonameids as $v) {
                    $supportednameids[] = $v;
                    $chp[] = array(
                        'name' => 'f[nameids][spsso][]', 'id' => 'f[nameids][spsso][]', 'value' => $v, 'checked' => TRUE);
                }
            }
            foreach ($allowednameids as $v) {
                if (!in_array($v, $supportednameids)) {
                    $chp[] = array('name' => 'f[nameids][spsso][]', 'id' => 'f[nameids][spsso][]', 'value' => $v, 'checked' => FALSE);
                }
            }
            $r .= '<div class="small-12 columns">';
            $r .= '<div class="small-3 large-3 columns">&nbsp;</div>';
            $r .= '<div class="small-8 large-7 columns nsortable">';
            foreach ($chp as $n) {
                $r .= '<div><label>' . form_checkbox($n) . ' ' . $n['value'] . '</label></div>';
            }
            $r .= '</div>';
            $r .= '<div class="columns"></div>';

            $r .= '</div>'; //row
            $result[] = '';
            $result[] = '<fieldset><legend>' . lang('rr_supnameids') . '</legend>' . $r . '</fieldset>';
            $result[] = '';

            /**
             * end nameids
             */
        }


        // algs global
        $result[] = '';
        $result[] = '<div class="section">Algorithms support</div>';
        $templatesDigest = j_DigestMethods();
        $templateSign = j_SignatureAlgorithms();
        $sesdigests = array();
        $sessignings = array();
        if ($sessform) {

            if (isset($ses['algs']['digest']) && is_array($ses['algs']['digest'])) {
                $sesdigests = $ses['algs']['digest'];
            }

            if (isset($ses['algs']['signing']) && is_array($ses['algs']['signing'])) {
                $sessignings = $ses['algs']['signing'];
            }
        } else {
            $extend = $ent->getExtendMetadata();
            if (!empty($extend)) {
                foreach ($extend as $ex) {
                    if ($ex->getType() === 'ent' && $ex->getNamespace() === 'alg') {

                        if ($ex->getElement() === 'DigestMethod') {
                            $sesdigests[] = $ex->getEvalue();
                        } elseif ($ex->getElement() === 'SigningMethod') {
                            $sessignings[] = $ex->getEvalue();
                        }
                    }
                }
            }
        }

        $digestmethods = '<div class="small-12 column"><div class="small-3 column"></div><div class="small-9 column">';

        foreach ($templatesDigest as $tmpdigest) {
            if (in_array($tmpdigest, $sesdigests)) {
                $digestmethods .= '<label><input type="checkbox" name="f[algs][digest][]" value="' . $tmpdigest . '" checked="checked"> ' . $tmpdigest . '</label>';
            } else {
                $digestmethods .= '<label><input type="checkbox" name="f[algs][digest][]" value="' . $tmpdigest . '" > ' . $tmpdigest . '</label>';
            }
        }

        $digestmethods .= '</div></div>';
        $result[] = '';

        $result[] = '<fieldset><legend>DigestMethods</legend>' . $digestmethods . '</fieldset>';
        $result[] = '';


        $signingmethods = '<div class="small-12 column"><div class="small-3 column"></div><div class="small-9 column">';
        foreach ($templateSign as $tmpsign) {
            if (in_array($tmpsign, $sessignings)) {
                $signingmethods .= '<label><input type="checkbox" name="f[algs][signing][]" value="' . $tmpsign . '" checked="checked"> ' . $tmpsign . '</label>';
            } else {
                $signingmethods .= '<label><input type="checkbox" name="f[algs][signing][]" value="' . $tmpsign . '" > ' . $tmpsign . '</label>';
            }
        }

        $signingmethods .= '</div></div>';


        $result[] = '';

        $result[] = '<fieldset><legend>SigningMethod</legend>' . $signingmethods . '</fieldset>';
        $result[] = '';


        $result[] = '';


        // end algs global

        return $result;
    }

    private function NgenerateLogoForm(models\Provider $ent, $ses = null)
    {
        $btnlangs = array('0' => lang('rr_unspecified')) + MY_Controller::$langselect;
        $type = $ent->getType();
        $sessform = FALSE;
        if (is_array($ses)) {
            $sessform = TRUE;
        }
        $logos = array('' . strtolower($type) . '' => array());
        if ($type === 'BOTH') {
            $logos = array('idp' => array(), 'sp' => array());
        }
        if (!$sessform) {
            $metaext = $ent->getExtendMetadata();
            $vid = 0;
            foreach ($metaext as $v) {
                $velement = $v->getElement();
                if (strcmp($velement, 'Logo') != 0) {
                    continue;
                }
                $attrs = $v->getAttributes();
                if (isset($attrs['xml:lang'])) {
                    $jlang = $attrs['xml:lang'];
                } else {
                    $jlang = 0;
                }
                $eid = $v->getId();
                if (empty($eid)) {
                    $eid = 'n' . $vid++;
                }
                $logos[$v->getType()]['' . $eid . ''] = array(
                    'url' => '' . $v->getLogoValue() . '',
                    'lang' => '' . $jlang . '',
                    'width' => $attrs['width'],
                    'height' => $attrs['height'],
                );
            }
        } else {
            if (strcasecmp('SP', $type) != 0 && isset($ses['uii']['idpsso']['logo'])) {
                log_message('debug', 'IDP POLO');
                foreach ($ses['uii']['idpsso']['logo'] as $k => $v) {
                    $size = explode('x', $v['size']);
                    $logos['idp']['' . $k . ''] = array(
                        'url' => $v['url'],
                        'lang' => $v['lang'],
                        'width' => $size[0],
                        'height' => $size[1],
                    );
                }
            }
            if (strcasecmp('IDP', $type) != 0 && isset($ses['uii']['spsso']['logo'])) {
                log_message('debug', 'SP POLO');
                foreach ($ses['uii']['spsso']['logo'] as $k => $v) {
                    $size = explode('x', $v['size']);
                    $logos['sp']['' . $k . ''] = array(
                        'url' => $v['url'],
                        'lang' => $v['lang'],
                        'width' => $size[0],
                        'height' => $size[1],
                    );
                }
            }
        }
        $result = array();
        foreach ($logos as $k1 => $v1) {
            $result[$k1][] = '';
            if (strcmp($k1, 'idp') == 0) {
                $t = 'idp';
            } elseif (strcmp($k1, 'sp') == 0) {
                $t = 'sp';
            }
            $p = '<ul class="small-block-grid-1">';
            foreach ($v1 as $k2 => $v2) {
                $p .= '<li class="small-12 columns">';
                $p .= '<div class="medium-3 columns"><img src="' . $v2['url'] . '" style="max-height: 100px;"/>';

                $p .= form_input(array(
                    'id' => 'f[uii][' . $t . 'sso][logo][' . $k2 . '][url]',
                    'name' => 'f[uii][' . $t . 'sso][logo][' . $k2 . '][url]',
                    'value' => $v2['url'],
                    'type' => 'hidden',
                ));
                $p .= form_input(array(
                    'id' => 'f[uii][' . $t . 'sso][logo][' . $k2 . '][lang]',
                    'name' => 'f[uii][' . $t . 'sso][logo][' . $k2 . '][lang]',
                    'value' => $v2['lang'],
                    'type' => 'hidden',
                ));
                $p .= form_input(array(
                    'id' => 'f[uii][' . $t . 'sso][logo][' . $k2 . '][size]',
                    'name' => 'f[uii][' . $t . 'sso][logo][' . $k2 . '][size]',
                    'value' => $v2['width'] . 'x' . $v2['height'],
                    'type' => 'hidden',
                ));
                $p .= '</div>';
                $p .= '<div class="medium-6 columns">';
                $p .= lang('rr_url') . ': ' . $v2['url'] . '<br />';
                if (empty($v2['lang'])) {
                    $l = lang('rr_unspecified');
                } else {
                    $l = $v2['lang'];
                }
                $p .= lang('rr_lang') . ': ' . $l . '<br />';
                $p .= lang('rr_size') . ': ' . $v2['width'] . 'x' . $v2['height'] . '';
                $p .= '</div>';
                $p .= '<div class="medium-3 columns"><button class="btn langinputrm inline left button tiny alert">' . lang('rr_remove') . '</button></div>';
                $p .= '</li>';
            }

            $z = '<li id="nlogo' . $t . 'row" class="small-12 columns" style="display: none;">';
            $z .= '<div class="medium-3 columns"><img src="" style="max-height: 100px;"/></div>';
            $z .= '<div class="medium-6 columns logoinfo"></div>';
            $z .= '<div class="medium-3 columns"><button class="btn langinputrm inline left button tiny alert">' . lang('rr_remove') . '</button></div>';
            $z .= '</li>';
            $p .= $z;
            $p .= '</ul>';
            $inlabel = '<div class="small-12 column"><div class="small-3 columns"><label class="right inline" for="logoretrieve">' . lang('rr_url') . '</label></div>';
            $in = '<div class="small-6 columns">' . form_input(array('name' => '' . $t . 'logoretrieve')) . '<small class="' . $t . 'logoretrieve error" style="display:none;"></small></div>';
            $in2 = '<div class="small-3 columns"><button type="button" name="' . $t . 'getlogo" class="button tiny getlogo" value="' . base_url() . 'ajax/checklogourl">' . lang('btngetlogo') . '</button></div></div>';

            $langselection = form_dropdown($t . 'logolang', $btnlangs);
            $reviewlogo = '<div class="small-3 column"><div class="logolangselect">' . $langselection . '</div><div class="logosizeinfo"></div></div><div class="small-6 column imgsource"></div><div class="small-3 column"><button class="button tiny addnewlogo" type="button" name="addnewlogo">' . lang('rr_add') . '</button></div>';
            $da = '<fieldset><legend>' . lang('rr_newlogosection') . '</legend>' . $inlabel . $in . $in2 . '<div id="' . $t . 'reviewlogo" class="small-12 column reviewlogo" style="display: none; max-height: 100px">' . $reviewlogo . '</div></fieldset>';

            $result[$k1][] = '<fieldset><legend>' . lang('rr_logoofservice') . '</legend>' . $p . ' ' . $da . '</fieldset>';
            $result[$k1][] = '';
        }
        return $result;
    }

    public function generateUIHintForm(models\Provider $ent, array $ses = null)
    {

        $sessform = (is_null($ses)) ? false : true;
        $type = $ent->getType();
        $result = array();
        $extendMetadata = $ent->getExtendMetadata();
        // BEGIN IDPSSO PART
        $enid = 0;
        if (strcasecmp($type, 'SP') != 0) {
            $ipHints = array();
            $domainHints = array();
            $geo = array();
            if ($sessform) {

                if (isset($ses['uii']['idpsso']['iphint']) && is_array($ses['uii']['idpsso']['iphint'])) {
                    $ipHints = $ses['uii']['idpsso']['iphint'];
                }
                if (isset($ses['uii']['idpsso']['domainhint']) && is_array($ses['uii']['idpsso']['domainhint'])) {
                    $domainHints = $ses['uii']['idpsso']['domainhint'];
                }
                if (isset($ses['uii']['idpsso']['geo']) && is_array($ses['uii']['idpsso']['geo'])) {
                    $geo = $ses['uii']['idpsso']['geo'];
                }
            } else {
                foreach ($extendMetadata as $e) {
                    $etype = $e->getType();
                    $enamespace = $e->getNamespace();
                    $eid = $e->getId();
                    if (empty($eid)) {
                        $eid = 'z' . $enid++;
                    }

                    if (strcmp($etype, 'idp') == 0 && strcasecmp($enamespace, 'mdui') == 0) {


                        $eelement = $e->getElement();
                        $evalue = $e->getEvalue();
                        if (strcasecmp($eelement, 'IPHint') == 0) {

                            $ipHints['' . $eid . ''] = trim($evalue);
                        } elseif (strcasecmp($eelement, 'DomainHint') == 0) {

                            $domainHints['' . $eid . ''] = trim($evalue);
                        } elseif (strcasecmp($eelement, 'GeolocationHint') == 0) {

                            $geo['' . $eid . ''] = trim($evalue);
                        }
                    }
                }
            }
            $r1 = '';
            $r2 = '';
            foreach ($domainHints as $k => $v) {

                $r1 .= '<div class="small-12 columns">';
                $r1 .= $this->_generateLangInputWithRemove('Domain Hint', 'f[uii][idpsso][domainhint][' . $k . ']', 'uiiidpssodomainhint', '' . $k . '', set_value('f[uii][idpsso][domainhint][' . $k . ']', $v, FALSE), '');
                $r1 .= '</div>';
            }
            $r1 .= '<div class="small-12 columns">';
            $spanclass = '';
            $r1 .= $this->_generateAddButton($spanclass, 'idpssoadddomainhint', 'idpssoadddomainhint', lang('btnadddomainhint'));
            $r1 .= '</div>';

            foreach ($ipHints as $k => $v) {

                $r2 .= '<div class="small-12 columns">';
                $r2 .= $this->_generateLangInputWithRemove('IP Hint', 'f[uii][idpsso][iphint][' . $k . ']', 'uiiidpssoiphint', '' . $k . '', set_value('f[uii][idpsso][iphint][' . $k . ']', $v), '');
                $r2 .= '</div>';
            }
            $r2 .= '<div class="small-12 columns">';
            $spanclass = '';
            $r2 .= $this->_generateAddButton($spanclass, 'idpssoaddiphint', 'idpssoaddiphint', lang('btnaddiphint'));
            $r2 .= '</div>';

            $result[] = '';
            $result[] = '<fieldset><legend>' . lang('e_idpdomainhint') . '</legend>' . $r1 . '</fieldset>';
            $result[] = '<fieldset><legend>' . lang('e_idpiphint') . '</legend>' . $r2 . '</fieldset>';
            $result[] = '';
            // start geolocation
            $geosinputs = '';
            foreach ($geo as $k => $g) {
                $geosinputs .= '<div class="small-12 column collapse georow"><div class="small-11 column"><input name="f[uii][idpsso][geo][' . $k . ']" type="text" value="' . html_escape($g) . '" readonly="readonly"></div><div class="small-1 column"><a href="#" class="rmgeo"><i class="fi-trash alert" style="color: red"></i></a></div></div>';
            }
            $r = '';
            $r .= '<div class="small-12 column">';
            $r .= '<div class="large-8 column" ><div class="row"><div class="small-10 column"><input id="latlng" type="text" placeholder="' . lang('rrclconmap1') . '"></div><div class="small-2 column"><a href="#" class="button tiny postfix" id="addlatlng">' . lang('rr_add') . '</a></div></div><div id="map-canvas" class="small-12 column" style="height: 400px;"></div></div>';
            $r .= '<div class="large-4 column"><input id="map-search" type="text" placeholder="' . lang('rrsearchonmap') . '"/><div id="geogroup">' . $geosinputs . '</div></div>';
            $r .= '</div>';
            $result[] = '<fieldset><legend>' . lang('rr_geolocation') . '</legend>' . $r . '</fieldset>';
            // end geolocation
        }
        // END IDPSSO PART
        return $result;
    }

    public function NgenerateUiiForm(models\Provider $ent, array $ses = null)
    {
        $logopart = $this->NgenerateLogoForm($ent, $ses);
        $langs = languagesCodes();
        $type = $ent->getType();
        $e = $ent->getExtendMetadata();
        $ext = array();
        if (!empty($e)) {
            foreach ($e as $value) {

                $ext['' . $value->getType() . '']['' . $value->getNamespace() . '']['' . $value->getElement() . ''][] = $value;
            }
        }
        $sessform = (is_null($ses)) ? false : true;
        $f_privacyurl = $ent->getPrivacyUrl();
        $p_privacyurl = $f_privacyurl;
        if ($sessform && array_key_exists('privacyurl', $ses)) {
            $p_privacyurl = $ses['privacyurl'];
        }
        $t_privacyurl = set_value('f[privacyurl]', $p_privacyurl, FALSE);
        if (!empty($t_privacyurl)) {
            $r = $this->_generateLabelInput(lang('rr_url'), 'f[privacyurl]', $t_privacyurl, '', FALSE, NULL);
            $result = array();
            $result[] = '';
            $result[] = '<div class="langgroup">' . lang('e_globalprivacyurl') . '<i><small> (' . lang('rr_default') . ') ' . lang('rr_optional') . '</small></i>' . showBubbleHelp('' . lang('rhelp_privacydefault1') . '') . '</div>';
            $result[] = $r;
            $result[] = '';
        }
        if ($type != 'SP') {
            /**
             * start display
             */
            if (strcasecmp($type, 'BOTH') == 0) {
                $result[] = '<div class="section">' . lang('identityprovider') . '</div>';
            }
            $result[] = '';
            $r = '';
            $langsdisplaynames = $langs;
            if (!$sessform && isset($ext['idp']['mdui']['DisplayName'])) {
                foreach ($ext['idp']['mdui']['DisplayName'] as $v1) {
                    $l = $v1->getAttributes();
                    if (isset($l['xml:lang'])) {
                        $jlang = $l['xml:lang'];
                        if (!array_key_exists($jlang, $langs)) {
                            log_message('error', 'Language code ' . $jlang . ' is not allowed for row (extendmetadaa) with id:' . $v1->getId());
                            $langtxt = $jlang;
                        } else {
                            $langtxt = $langs['' . $jlang . ''];
                            unset($langsdisplaynames['' . $jlang . '']);
                        }
                    } else {
                        log_message('error', 'Language not set for extendmetada row with id:' . $v1->getId());
                        continue;
                    }
                    $origval = $v1->getEvalue();
                    $nval = $origval;
                    if ($sessform && isset($ses['uii']['idpsso']['displayname']['' . $jlang . ''])) {
                        $nval = $ses['uii']['idpsso']['displayname']['' . $jlang . ''];
                        unset($ses['uii']['idpsso']['displayname']['' . $jlang . '']);
                    }
                    $currval = set_value('f[uii][idpsso][displayname][' . $jlang . ']', $nval, FALSE);
                    $r .= '<div class="small-12 columns">';
                    $r .= $this->_generateLangInputWithRemove($langtxt, 'f[uii][idpsso][displayname][' . $jlang . ']', 'uiiidpssodisplayname', '' . $jlang . '', $currval, '');
                    $r .= '</div>';
                }
            }
            if ($sessform && isset($ses['uii']['idpsso']['displayname']) && is_array($ses['uii']['idpsso']['displayname'])) {

                foreach ($ses['uii']['idpsso']['displayname'] as $key => $value) {
                    if (!array_key_exists($key, $langs)) {
                        log_message('error', 'Language code ' . $key . ' is not allowed for row (extendmetadaa)');
                        $langtxt = $key;
                    } else {
                        $langtxt = $langs['' . $key . ''];
                    }
                    $r .= '<div class="small-12 columns">';
                    $tmpvalue = set_value('f[uii][idpsso][displayname][' . $key . ']', $value, FALSE);
                    $r .= $this->_generateLangInputWithRemove($langtxt, 'f[uii][idpsso][displayname][' . $key . ']', 'uiiidpssodisplayname', $key, $tmpvalue, '');
                    $r .= '</div>';
                    unset($langsdisplaynames['' . $key . '']);
                }
            }
            $r .= '<div class="small-12 columns">';
            $r .= $this->_generateLangAddButton('idpuiidisplayadd', 'idpuiidisplaylangcode', MY_Controller::$langselect, 'idpadduiidisplay', 'idpadduiidisplay');
            $r .= '</div>';
            $result[] = '<fieldset><legend>' . lang('e_idpservicename') . '</legend>' . $r . '</fieldset>';
            $result[] = '';

            /**
             * end display
             */
            /**
             * start description
             */
            $result[] = '';
            $r = '';
            $langsdisplaynames = $langs;
            if (!$sessform && isset($ext['idp']['mdui']['Description'])) {
                foreach ($ext['idp']['mdui']['Description'] as $v1) {
                    $l = $v1->getAttributes();
                    if (isset($l['xml:lang'])) {
                        $jlang = $l['xml:lang'];
                        if (!array_key_exists($jlang, $langs)) {
                            log_message('error', 'Language code ' . $jlang . ' is not allowed for row (extendmetadaa) with id:' . $v1->getId());
                            $langtxt = $jlang;
                        } else {
                            $langtxt = $langs['' . $jlang . ''];
                            unset($langsdisplaynames['' . $jlang . '']);
                        }
                    } else {
                        log_message('error', 'Language not set for extendmetada row with id:' . $v1->getId());
                        continue;
                    }
                    $origval = $v1->getEvalue();
                    $nval = $origval;
                    if ($sessform && isset($ses['uii']['idpsso']['desc']['' . $jlang . ''])) {
                        $nval = $ses['uii']['idpsso']['desc']['' . $jlang . ''];
                        unset($ses['uii']['idpsso']['desc']['' . $jlang . '']);
                    }
                    $currval = set_value('f[uii][idpsso][desc][' . $jlang . ']', $nval, FALSE);
                    $r .= '<div class="small-12 columns">';
                    $r .= $this->_generateLangTextareaWithRemove($langtxt, 'f[uii][idpsso][desc][' . $jlang . ']', 'lhelpdesk', $jlang, $currval, '');
                    $r .= '</div>';
                }
            }
            if ($sessform && isset($ses['uii']['idpsso']['desc']) && is_array($ses['uii']['idpsso']['desc'])) {
                foreach ($ses['uii']['idpsso']['desc'] as $key => $value) {
                    $r .= '<div class="small-12 columns">';
                    $r .= $this->_generateLangTextareaWithRemove($langs['' . $key . ''], 'f[uii][idpsso][desc][' . $key . ']', 'lhelpdesk', $key, set_value('f[uii][idpsso][desc][' . $key . ']', $value, FALSE), '');
                    $r .= '</div>';
                    unset($langsdisplaynames['' . $key . '']);
                }
            }
            $r .= '<div class="small-12 columns">';
            $r .= $this->_generateLangAddButton('idpuiidescadd', 'idpuiidesclangcode', MY_Controller::$langselect, 'idpadduiidesc', '' . lang('rr_description') . '');
            $r .= '</div>';
            $result[] = '<fieldset><legend>' . lang('e_idpservicedesc') . '</legend>' . $r . '</fieldset>';
            $result[] = '';
            /**
             * end description
             */
            /**
             * start helpdesk
             */
            $result[] = '';
            $r = '';
            $langsdisplaynames = $langs;
            if (!$sessform && isset($ext['idp']['mdui']['InformationURL'])) {
                foreach ($ext['idp']['mdui']['InformationURL'] as $v1) {
                    $l = $v1->getAttributes();
                    if (isset($l['xml:lang'])) {
                        $jlang = $l['xml:lang'];
                        if (!array_key_exists($jlang, $langs)) {
                            log_message('error', 'Language code ' . $jlang . ' is not allowed for row (extendmetadaa) with id:' . $v1->getId());
                            $langtxt = $jlang;
                        } else {
                            $langtxt = $langs['' . $jlang . ''];
                            unset($langsdisplaynames['' . $jlang . '']);
                        }
                    } else {
                        log_message('error', 'Language not set for extendmetada row with id:' . $v1->getId());
                        continue;
                    }
                    $origval = $v1->getEvalue();


                    $r .= '<div class="small-12 columns">';
                    $r .= $this->_generateLangInputWithRemove($langtxt, 'f[uii][idpsso][helpdesk][' . $jlang . ']', 'uiiidpssohelpdesk', $jlang, $origval, '');
                    $r .= '</div>';
                }
            }
            if ($sessform && isset($ses['uii']['idpsso']['helpdesk']) && is_array($ses['uii']['idpsso']['helpdesk'])) {
                foreach ($ses['uii']['idpsso']['helpdesk'] as $key => $value) {
                    if (!array_key_exists($key, $langs)) {
                        log_message('error', 'Language code ' . $key . ' is not allowed for row (extendmetadaa) with id:' . $v1->getId());
                        $langtxt = $key;
                    } else {
                        $langtxt = $langs['' . $key . ''];
                    }
                    $r .= '<div class="small-12 columns">';
                    $r .= $this->_generateLangInputWithRemove($langtxt, 'f[uii][idpsso][helpdesk][' . $key . ']', 'uiiidpssohelpdesk', $key, set_value('f[uii][idpsso][helpdesk][' . $key . ']', $value, FALSE), '');
                    $r .= '</div>';
                    unset($langsdisplaynames['' . $key . '']);
                }
            }
            $r .= '<div class="small-12 columns">';
            $r .= $this->_generateLangAddButton('idpuiihelpdeskadd', 'idpuiihelpdesklangcode', MY_Controller::$langselect, 'idpadduiihelpdesk', 'idpadduiihelpdesk');
            $r .= '</div>';
            $result[] = '<fieldset><legend>' . lang('e_idpserviceinfourl') . '</legend>' . $r . '</fieldset>';
            $result[] = '';

            /**
             * end helpdesk
             */
            /**
             * start privacy url
             */
            $result[] = '';
            $r = '';
            $origs = array();
            $sorig = array();
            $langsdisplaynames = $langs;
            if (!$sessform && isset($ext['idp']['mdui']['PrivacyStatementURL'])) {
                foreach ($ext['idp']['mdui']['PrivacyStatementURL'] as $value) {
                    $l = $value->getAttributes();
                    $origs['' . $l['xml:lang'] . ''] = array('url' => $value->getEvalue());
                }
                $sorig = $origs;
            }
            if ($sessform && isset($ses['prvurl']['idpsso'])) {
                foreach ($ses['prvurl']['idpsso'] as $k2 => $v2) {
                    $sorig['' . $k2 . ''] = array('url' => $v2);
                }
            }
            foreach ($sorig as $k3 => $v3) {
                if (array_key_exists($k3, $origs)) {
                    if ($origs['' . $k3 . '']['url'] === $v3['url']) {
                        $sorig['' . $k3 . '']['notice'] = '';
                    } else {
                        $sorig['' . $k3 . '']['notice'] = 'notice';
                    }
                } else {
                    $sorig['' . $k3 . '']['notice'] = 'notice';
                }
            }
            foreach ($sorig as $k4 => $v4) {
                $r .= '<div class="small-12 columns">';
                $r .= $this->_generateLangInputWithRemove($langsdisplaynames['' . $k4 . ''], 'f[prvurl][idpsso][' . $k4 . ']', 'prvurlidpsso', $k4, $v4['url'], '');
                $r .= '</div>';
            }
            $r .= '<div class="small-12 columns">';
            $r .= $this->_generateLangAddButton('addlprivacyurlidpsso localized', 'langcode', MY_Controller::$langselect, 'addlprivacyurlidpsso', 'addlprivacyurlidpsso');
            $r .= '</div>';

            $result[] = '<fieldset><legend>' . lang('e_idpserviceprivacyurl') . '</legend>' . $r . '</fieldset>';
            $result[] = '';

            /**
             * end privacy url
             */
            foreach ($logopart['idp'] as $v) {
                $result[] = $v;
            }


        }
        if ($type != 'IDP') {


            /**
             * start display
             */
            if (strcasecmp($type, 'BOTH') == 0) {
                $result[] = '<div class="section">' . lang('serviceprovider') . '</div>';
            }
            $result[] = '';
            $r = '';
            $langsdisplaynames = $langs;
            if (!$sessform && isset($ext['sp']['mdui']['DisplayName'])) {
                foreach ($ext['sp']['mdui']['DisplayName'] as $v1) {
                    $l = $v1->getAttributes();
                    if (isset($l['xml:lang'])) {
                        $jlang = $l['xml:lang'];
                        if (!array_key_exists($jlang, $langs)) {
                            log_message('error', 'Language code ' . $jlang . ' is not allowed for row (extendmetadaa) with id:' . $v1->getId());
                            $langtxt = $jlang;
                        } else {
                            $langtxt = $langs['' . $jlang . ''];
                            unset($langsdisplaynames['' . $jlang . '']);
                        }
                    } else {
                        log_message('error', 'Language not set for extendmetada row with id:' . $v1->getId());
                        continue;
                    }
                    $origval = $v1->getEvalue();
                    $nval = $origval;
                    if ($sessform && isset($ses['uii']['spsso']['displayname']['' . $jlang . ''])) {
                        $nval = $ses['uii']['spsso']['displayname']['' . $jlang . ''];
                        unset($ses['uii']['spsso']['displayname']['' . $jlang . '']);
                    }
                    $currval = set_value('f[uii][spsso][displayname][' . $jlang . ']', $nval, FALSE);
                    $r .= '<div class="small-12 columns">';
                    $r .= $this->_generateLangInputWithRemove($langtxt, 'f[uii][spsso][displayname][' . $jlang . ']', 'uiispssodisplayname', $jlang, $currval, '');
                    $r .= '</div>';
                }
            }
            if ($sessform && isset($ses['uii']['spsso']['displayname']) && is_array($ses['uii']['spsso']['displayname'])) {
                foreach ($ses['uii']['spsso']['displayname'] as $key => $value) {
                    $r .= '<div class="small-12 columns">';
                    if (isset($langs['' . $key . ''])) {
                        $langtxt = $langs['' . $key . ''];
                    } else {
                        $langtxt = $key;
                    }
                    $r .= $this->_generateLangInputWithRemove($langtxt, 'f[uii][spsso][displayname][' . $key . ']', 'uiispssodisplayname', $key, set_value('f[uii][spsso][displayname][' . $key . ']', $value, FALSE), '');
                    $r .= '</div>';
                    unset($langsdisplaynames['' . $key . '']);
                }
            }

            $r .= '<div class="small-12 columns">';
            $r .= $this->_generateLangAddButton('spuiidisplayadd', 'spuiidisplaylangcode', MY_Controller::$langselect, 'spadduiidisplay', 'spadduiidisplay');
            $r .= '</div>';
            $result[] = '<fieldset><legend>' . lang('e_spservicename') . '</legend>' . $r . '</fieldset>';
            $result[] = '';

            /**
             * end display
             */
            /**
             * start helpdesk
             */
            $result[] = '';
            $r = '';
            $langsdisplaynames = $langs;
            if (!$sessform && isset($ext['sp']['mdui']['InformationURL'])) {
                foreach ($ext['sp']['mdui']['InformationURL'] as $v1) {
                    $l = $v1->getAttributes();
                    if (isset($l['xml:lang'])) {
                        $jlang = $l['xml:lang'];
                        if (!array_key_exists($jlang, $langs)) {
                            log_message('error', 'Language code ' . $jlang . ' is not allowed for row (extendmetadaa) with id:' . $v1->getId());
                            $langtxt = $jlang;
                        } else {
                            $langtxt = $langs['' . $jlang . ''];
                            unset($langsdisplaynames['' . $jlang . '']);
                        }
                    } else {
                        log_message('error', 'Language not set for extendmetada row with id:' . $v1->getId());
                        continue;
                    }
                    $origval = $v1->getEvalue();
                    $nval = $origval;
                    if ($sessform && isset($ses['uii']['spsso']['helpdesk']['' . $jlang . ''])) {
                        $nval = $ses['uii']['spsso']['helpdesk']['' . $jlang . ''];
                        unset($ses['uii']['spsso']['helpdesk']['' . $jlang . '']);
                    }
                    $currval = set_value('f[uii][spsso][helpdesk][' . $jlang . ']', $nval, FALSE);
                    $r .= '<div class="small-12 columns">';
                    $r .= $this->_generateLangInputWithRemove($langtxt, 'f[uii][spsso][helpdesk][' . $jlang . ']', 'uiispssohelpdesk', $jlang, $currval, '');
                    $r .= '</div>';
                }
            }
            if ($sessform && isset($ses['uii']['spsso']['helpdesk']) && is_array($ses['uii']['spsso']['helpdesk'])) {
                foreach ($ses['uii']['spsso']['helpdesk'] as $key => $value) {
                    $r .= '<div class="small-12 columns">';
                    if (isset($langs['' . $key . ''])) {
                        $langtxt = $langs['' . $key . ''];
                    } else {
                        $langtxt = $key;
                    }
                    $r .= $this->_generateLangInputWithRemove($langtxt, 'f[uii][spsso][helpdesk][' . $key . ']', 'uiispssohelpdesk', $key, set_value('f[uii][spsso][helpdesk][' . $key . ']', $value, FALSE), '');
                    $r .= '</div>';
                    unset($langsdisplaynames['' . $key . '']);
                }
            }
            $r .= '<div class="small-12 columns">';
            $r .= $this->_generateLangAddButton('spuiihelpdeskadd', 'spuiihelpdesklangcode', MY_Controller::$langselect, 'spadduiihelpdesk', 'spadduiihelpdesk');
            $r .= '</div>';

            $result[] = '<fieldset><legend>' . lang('e_spserviceinfourl') . '</legend>' . $r . '</fieldset>';
            $result[] = '';
            /**
             * end helpdesk
             */
            /**
             * start description
             */
            $result[] = '';
            $r = '';
            $langsdisplaynames = $langs;
            if (!$sessform && isset($ext['sp']['mdui']['Description'])) {
                foreach ($ext['sp']['mdui']['Description'] as $v1) {
                    $l = $v1->getAttributes();
                    if (isset($l['xml:lang'])) {
                        $jlang = $l['xml:lang'];
                        if (!array_key_exists($jlang, $langs)) {
                            log_message('error', 'Language code ' . $jlang . ' is not allowed for row (extendmetadaa) with id:' . $v1->getId());
                            $langtxt = $jlang;
                        } else {
                            $langtxt = $langs['' . $jlang . ''];
                            unset($langsdisplaynames['' . $jlang . '']);
                        }
                    } else {
                        log_message('error', 'Language not set for extendmetada row with id:' . $v1->getId());
                        continue;
                    }
                    $origval = $v1->getEvalue();
                    $nval = $origval;
                    if ($sessform && isset($ses['uii']['spsso']['desc']['' . $jlang . ''])) {
                        $nval = $ses['uii']['spsso']['desc']['' . $jlang . ''];
                        unset($ses['uii']['spsso']['desc']['' . $jlang . '']);
                    }
                    $currval = set_value('f[uii][spsso][desc][' . $jlang . ']', $nval, FALSE);
                    $r .= '<div class="small-12 columns">';
                    $r .= $this->_generateLangTextareaWithRemove($langtxt, 'f[uii][spsso][desc][' . $jlang . ']', 'uiispssodesc', $jlang, $currval, '');
                    $r .= '</div>';
                }
            }
            if ($sessform && isset($ses['uii']['spsso']['desc']) && is_array($ses['uii']['spsso']['desc'])) {
                foreach ($ses['uii']['spsso']['desc'] as $key => $value) {
                    $r .= '<div class="small-12 columns">';
                    $r .= $this->_generateLangTextareaWithRemove($langs['' . $key . ''], 'f[uii][spsso][desc][' . $key . ']', 'uiispssodesc', $key, set_value('f[uii][spsso][desc][' . $key . ']', $value, FALSE), '');
                    $r .= '</div>';
                    unset($langsdisplaynames['' . $key . '']);
                }
            }
            $r .= '<div class="small-12 columns">';
            $r .= $this->_generateLangAddButton('spuiidescadd', 'spuiidesclangcode', MY_Controller::$langselect, 'spadduiidesc', 'spadduiidesc');
            $r .= '</div>';
            $result[] = '<fieldset><legend>' . lang('e_spservicedesc') . '</legend>' . $r . '</fieldset>';
            $result[] = '';
            /**
             * end description
             */
            /**
             * start privacy url
             */
            $result[] = '';
            $r = '';
            $origs = array();
            $sorig = array();
            $langsdisplaynames = $langs;
            if (!$sessform && isset($ext['sp']['mdui']['PrivacyStatementURL'])) {
                foreach ($ext['sp']['mdui']['PrivacyStatementURL'] as $value) {
                    $l = $value->getAttributes();
                    $origs['' . $l['xml:lang'] . ''] = array('url' => $value->getEvalue());
                }
                $sorig = $origs;
            }
            if ($sessform && isset($ses['prvurl']['spsso'])) {
                foreach ($ses['prvurl']['spsso'] as $k2 => $v2) {
                    $sorig['' . $k2 . ''] = array('url' => $v2);
                }
            }
            foreach ($sorig as $k3 => $v3) {
                if (array_key_exists($k3, $origs)) {
                    if ($origs['' . $k3 . '']['url'] === $v3['url']) {
                        $sorig['' . $k3 . '']['notice'] = '';
                    } else {
                        $sorig['' . $k3 . '']['notice'] = 'notice';
                    }
                } else {
                    $sorig['' . $k3 . '']['notice'] = 'notice';
                }
            }
            foreach ($sorig as $k4 => $v4) {
                $r .= '<div class="small-12 columns">';
                $r .= $this->_generateLangInputWithRemove($langsdisplaynames['' . $k4 . ''], 'f[prvurl][spsso][' . $k4 . ']', 'prvurlspsso', $k4, $v4['url'], '');
                $r .= '</div>';
            }
            $r .= '<div class="small-12 columns">';


            $r .= $this->_generateLangAddButton('addlprivacyurlspsso localized', 'langcode', MY_Controller::$langselect, 'addlprivacyurlspsso', 'addlprivacyurlspsso');
            $r .= '</div>';

            $result[] = '<fieldset><legend>' . lang('e_spserviceprivacyurl') . '</legend>' . $r . '</fieldset>';
            $result[] = '';

            /**
             * end privacy url
             */
            foreach ($logopart['sp'] as $v) {
                $result[] = $v;
            }
        }
        return $result;
    }

    /**
     * by default we just get all federations
     * @param null $conditions
     * @return array
     * @todo add more if conditions are set in array
     */
    public function getFederation($conditions = null)
    {
        $result = array();
        $feds = new models\Federations;
        $fedCollection = $feds->getFederations();
        if (!empty($fedCollection)) {
            $result[''] = lang('rr_pleaseselect');
            foreach ($fedCollection as $key) {
                $value = "";
                $is_activ = $key->getActive();
                if (!($is_activ)) {
                    $value .= "inactive";
                }

                if (!empty($value)) {
                    $value = "(" . $value . ")";
                }
                $result[$key->getName()] = $key->getName() . " " . $value;
            }
        } else {
            $result[''] = lang('rr_nofedfound');
        }
        return $result;
    }


    public function generateFederationsElement($federations)
    {
        $result = "";
        $list = array();
        foreach ($federations as $f) {
            $list[$f->getId()] = $f->getName();
        }
        $result .= form_dropdown('fedid', $list, set_value('fedid'));
        return $result;
    }


    public function generateFederationEditForm(models\Federation $federation)
    {
        $f = '<div class="small-12 columns">';
        $f .= jGenerateInput(lang('rr_fed_name'), 'fedname', set_value('fedname', $federation->getName(), '', FALSE), '');
        $f .= '</div>';


        $f .= '<div class="small-12 columns">';

        $useal = $federation->getAltMetaUrlEnabled();
        if ($useal) {
            $usealtmeta = 'ext';
        } else {
            $usealtmeta = 'loc';
        }
        $radios = array(
            array('value' => 'loc', 'label' => lang('lblusejaggermetaurl') . ':<br />' . base_url() . 'signedmetadata/federation/' . $federation->getSysname() . '/metadata.xml'),
            array('value' => 'ext', 'label' => lang('lbluseothermetaurl')),
        );
        $f .= jGenerateRadios(lang('metapublicationurl'), 'usealtmeta', $radios, set_value('usealtmeta', $usealtmeta), '');
        $f .= '</div>';
        $f .= '<div class="small-12 columns">';

        $f .= jGenerateInput('', 'altmetaurl', set_value('altmetaurl', $federation->getAltMetaUrl(), FALSE), 'alert', lang('metaalturlinput'));
        $f .= '</div>';

        $f .= '<div class="small-12 columns">';
        $f .= jGenerateInput(lang('fednameinmeta'), 'urn', set_value('urn', $federation->getUrn(), FALSE), '', FALSE);
        $f .= '</div>';
        $f .= '<div class="small-12 columns">';
        $f .= jGenerateInput(lang('rr_fed_descid'), 'descid', set_value('descid', $federation->getDescriptorId(), FALSE), '', 'leave it empty if you want it be genereated dynamicaly');
        $f .= '</div>';

        $f .= '<div class="small-12 columns">';
        $f .= jGenerateInput(lang('rr_fed_publisher'), 'publisher', set_value('publisher', $federation->getPublisher(), FALSE), '');
        $f .= '</div>';

        $f .= '<div class="small-12 columns">';
        $f .= '<div class="small-3 columns text-right">' . form_label(lang('rr_isfedpublic') . ' ' . showBubbleHelp(lang('rhelppublicfed')), 'ispublic') . '</div><div class="small-8 large-7 columns end">' . form_checkbox('ispublic', 'accept', set_value('ispublic', $federation->getPublic())) . '</div>';
        $f .= '</div>';

        $f .= '<div class="small-12 columns">';
        $f .= '<div class="small-3 columns text-right">' . form_label(lang('rr_lexport_enabled'), 'lexport') . '</div><div class="small-8 large-7 columns">' . form_checkbox('lexport', 'accept', set_value('lexport', $federation->getLocalExport())) . '</div><div class="small-1 large-2 "></div>';
        $f .= '</div>';

        $f .= '<div class="small-12 columns">';

        $f .= jGenerateDropdown(lang('digestmethodsign'), 'digestmethod', array('SHA-1' => 'SHA-1', 'SHA-256' => 'SHA-256'), set_value('digestmethod', $federation->getDigest()), '');

        $f .= '</div>';

        $f .= '<div class="small-12 columns">';

        $f .= jGenerateDropdown(lang('digestmethodexportsign'), 'digestmethodext', array('SHA-1' => 'SHA-1', 'SHA-256' => 'SHA-256'), set_value('digestmethodext', $federation->getDigestExport()), '');


        $f .= '</div>';

        $f .= '<div class="small-12 columns">';


        $f .= jGenerateTextarea(lang('rr_description'), 'description', set_value('description', $federation->getDescription(), FALSE), '');

        $f .= '<div class="small-1 large-2 "></div>';
        $f .= '</div>';
        $f .= '<div class="small-12 columns">';


        $f .= jGenerateTextarea(lang('rr_fed_tou'), 'tou', set_value('tou', $federation->getTou(), FALSE), '');

        $f .= '<div class="small-1 large-2 "></div>';
        $f .= '</div>';
        return $f;
    }

    public function excludedArpsForm(models\Provider $idp)
    {
        $tmp_providers = new models\Providers();
        $excluded = $idp->getExcarps();
        $members = $tmp_providers->getCircleMembersLight($idp);
        $membersInArray = array();
        foreach ($members as $m) {
            $membersInArray[] = $m->getEntityId();
        }
        $membersInArray = array_diff_assoc($membersInArray, $excluded);
        $rows = array();
        foreach ($excluded as $v) {

            $rows[] = '<input type="checkbox" name="exc[]"  checked="checked"   value="' . $v . '" /><label for="' . $v . '">' . html_escape($v) . '</label>';
        }
        foreach ($membersInArray as $v) {
            $rows[] = '<input type="checkbox" name="exc[]" value="' . $v . '"  /><label for="' . $v . '">' . html_escape($v) . '</label>';
        }
        return $rows;
    }

    public function supportedAttributesForm(models\Provider $idp)
    {
        $tmp = new models\Attributes();
        $attributes_defs = $tmp->getAttributes();
        if (empty($attributes_defs)) {
            log_message('error', 'There is no attributes definitions');
            return null;
        }
        $tmp1 = new models\AttributeReleasePolicies();
        $supported = $tmp1->getSupportedAttributes($idp);
        $data = array();
        foreach ($attributes_defs as $a) {
            $data[$a->getId()] = array('s' => 0, 'name' => $a->getName(), 'attrid' => $a->getId());
        }
        if (!empty($supported)) {

            foreach ($supported as $s) {
                $data[$s->getAttribute()->getId()]['s'] = 1;
            }
        }

        $result_top = "";
        $result_bottom = "";
        $result = '<table id="details">';
        $result .= '<thead><tr><th>' . lang('rr_attr_name') . '</th><th>' . lang('rr_supported') . '</th></tr></thead>';
        foreach ($data as $d => $value) {
            if ($value['s'] == 1) {
                $f = form_checkbox('attr[' . $value['attrid'] . ']', '1', true);
                $result_top .= '<tr><td>' . $value['name'] . '</td><td>' . $f . '</td></tr>';
            } else {
                $f = form_checkbox('attr[' . $value['attrid'] . ']', '1', false);
                $result_bottom .= '<tr><td>' . $value['name'] . '</td><td>' . $f . '</td></tr>';
            }
        }
        if (!empty($result_top)) {
            $result_top = '<tbody class="attr_supported">' . $result_top . '</tbody>';
        }
        if (!empty($result_bottom)) {
            $result_bottom = '<tbody>' . $result_bottom . '</tbody>';
        }
        $result .= $result_top . $result_bottom . '</table>';
        return $result;
    }

    public function generateEditPolicyForm(models\AttributeReleasePolicy $arp, $action = null, $submit_type = null)
    {
        $result = '';
        $attributes = array('id' => 'formver2');
        $type = $arp->getType();
        $hidden = array('idpid' => $arp->getProvider()->getId(), 'attribute' => $arp->getAttribute()->getId(), 'requester' => $arp->getRequester());
        if ($type == 'fed') {
            $hidden['fedid'] = $arp->getRequester();
        }
        if (empty($action)) {
            $action = base_url() . 'manage/attributepolicy/submit';
        }
        $result .= form_open($action, $attributes, $hidden);
        $result .= $this->generateEditPolicyFormElement($arp);
        $result .= '<div>';
        if (!empty($submit_type) && $submit_type == 'create') {
            $btns = array(
                '<button name="submit" type="submit" value="cancel" class="resetbutton reseticon alert">' . lang('rr_cancel') . '</button>',
                '<button name="submit" type="submit" value="create" class="savebutton saveicon">' . lang('rr_create') . '</button>'
            );

        } else {
            $btns = array(
                '<button name="submit" type="submit" value="delete" class="resetbutton reseticon alert">' . lang('rr_remove') . '</button>',
                '<button name="submit" type="submit" value="modify" class="savebutton saveicon">' . lang('rr_modify') . '</button>'
            );

        }
        $result .= revealBtnsRow($btns);
        $result .= '</div>';
        $result .= form_close();
        return $result;
    }

    public function generateEditPolicyFormElement(models\AttributeReleasePolicy $arp)
    {
        $result = form_fieldset(lang('rr_attr_name') . ': ' . $arp->getAttribute()->getFullName() . ' (' . $arp->getAttribute()->getName() . ')');
        $result .= '<div class="small-12 columns"><div class="medium-3 columns">' . form_label(lang('rr_setpolicy'), 'policy') . '</div><div class="medium-7 columns end">';
        $result .= form_dropdown('policy', $this->ci->config->item('policy_dropdown'), $arp->getPolicy()) . '</div></div>';
        $result .= form_fieldset_close();
        return $result;
    }

    public function generateAddRegpol()
    {
        $langs = languagesCodes();
        $langselected = set_value('regpollang', $this->defaultlangselect);
        $result = '<div class="small-12 columns"><div class="small-3 columns"><label for="name" class="inline right">' . lang('rr_displayname') . '</label></div><div class="small-6 large-7 columns end">' . form_input('name', set_value('name', '', FALSE)) . '</div></div>';
        $result .= '<div class="small-12 columns"><div class="small-3 columns"><label for="regpollang" class="inline right">' . lang('regpol_language') . '</label></div><div class="small-6 large-7 columns end">' . form_dropdown('regpollang', $langs, $langselected) . '</div></div>';
        $result .= '<div class="small-12 columns"><div class="small-3 columns"><label for="url" class="inline right">' . lang('entcat_url') . '</label></div><div class="small-6 large-7 columns end">' . form_input('url', set_value('url', '', FALSE)) . '</div></div>';
        $result .= '<div class="small-12 columns"><div class="small-3 columns"><label for="description" class="inline right">' . lang('entcat_description') . '</label></div><div class="small-6 large-7 columns end">' . form_textarea('description', set_value('description', '', FALSE)) . '</div></div>';
        $result .= '<div class="small-12 columns"><div class="small-3 columns"><label for="cenabled" class="inline right">' . lang('entcat_enabled') . '</label></div><div class="small-6 large-7 columns end">' . form_checkbox('cenabled', 'accept') . '</div></div>';
        return $result;
    }

    public function generateEditRegpol(models\Coc $coc)
    {
        $langs = languagesCodes();
        $langset = $coc->getLang();
        if (!empty($langset)) {
            if (!array_key_exists($langset, $langs)) {
                $langs['' . $langset . ''] = $langset;
            }
        }
        $langselected = $langset;
        $result = '<div class="small-12 columns"><div class="small-3 columns"><label for="name" class="inline right">' . lang('rr_displayname') . '</label></div><div class="small-6 large-7 columns end">' . form_input('name', set_value('name', $coc->getName(), FALSE)) . '</div></div>' .
            '<div class="small-12 columns"><div class="small-3 columns"><label for="regpollang" class="inline right">' . lang('regpol_language') . '</label></div><div class="small-6 large-7 columns end">' . form_dropdown('regpollang', $langs, $langselected) . '</div></div>' .
            '<div class="small-12 columns"><div class="small-3 columns"><label for="url" class="inline right">' . lang('entcat_url') . '</label></div><div class="small-6 large-7 columns end">' . form_input('url', set_value('url', $coc->getUrl(), FALSE)) . '</div></div>' .
            '<div class="small-12 columns"><div class="small-3 columns"><label for="description" class="inline right">' . lang('entcat_description') . '</label></div><div class="small-6 large-7 columns end">' . form_textarea('description', set_value('description', $coc->getDescription(), FALSE)) . '</div></div>' .
            '<div class="small-12 columns"><div class="small-3 columns"><label for="cenabled" class="inline right">' . lang('entcat_enabled') . '</label></div><div class="small-6 large-7 columns end">' . form_checkbox('cenabled', 'accept', set_value('cenabled', $coc->getAvailable())) . '</div></div>';
        return $result;
    }

    public function generateAddCoc()
    {
        $attrsnames = attrsEntCategoryList();
        $attrdropdown = array();
        foreach ($attrsnames as $k) {
            $attrdropdown['' . $k . ''] = $k;
        }
        $result = '<div class="small-12 columns"><div class="small-3 columns"><label for="name" class="inline right">' . lang('entcat_displayname') . '</label></div><div class="small-6 large-7 columns end">' . form_input('name', set_value('name')) . '</div></div>';
        $result .= '<div class="small-12 columns"><div class="small-3 columns"><label for="attrname" class="inline right">' . lang('rr_attr_name') . '</label></div><div class="small-6 large-7 columns end">' . form_dropdown('attrname', $attrdropdown, set_value('attrname')) . '</div></div>';
        $result .= '<div class="small-12 columns"><div class="small-3 columns"><label for="url" class="inline right">' . lang('entcat_value') . '</label></div><div class="small-6 large-7 columns end">' . form_input('url', set_value('url', '', FALSE)) . '</div></div>';
        $result .= '<div class="small-12 columns"><div class="small-3 columns"><label for="cenabled" class="right">' . lang('entcat_enabled') . '</label></div><div class="small-6 large-7 columns end">' . form_checkbox('cenabled', 'accept') . '</div></div>';
        $result .= '<div class="small-12 columns"><div class="small-3 columns"><label for="description" class="inline right">' . lang('entcat_description') . '</label></div><div class="small-6 large-7 columns end">' . form_textarea('description', set_value('description')) . '</div></div>';
        return $result;
    }

    public function generateEditCoc(models\Coc $coc)
    {
        $attrsnames = attrsEntCategoryList();
        $attrdropdown = array();
        foreach ($attrsnames as $k) {
            $attrdropdown['' . $k . ''] = $k;
        }
        $result = '<div class="small-12 columns">' . jGenerateInput(lang('entcat_displayname'), 'name', set_value('name', $coc->getName()), '') . '</div>';
        $result .= '<div class="small-12 columns">' . jGenerateInput(lang('entcat_value'), 'url', set_value('url', $coc->getUrl(), FALSE), '') . '</div>';
        $result .= '<div class="small-12 columns">' . jGenerateDropdown(lang('rr_attr_name'), 'attrname', $attrdropdown, $coc->getSubtype(), '') . '</div>';
        $result .= '<div class="small-12 columns"><div class="small-3 columns"><label for="cenabled" class="right">' . lang('entcat_enabled') . '</label></div><div class="small-6 large-7 columns end">' . form_checkbox('cenabled', 'accept', set_value('cenabled', $coc->getAvailable())) . '</div></div>';
        $result .= '<div class="small-12 columns"><div class="small-3 columns"><label for="description" class="inline right">' . lang('entcat_description') . '</label></div><div class="small-6 large-7 columns end">' . form_textarea('description', set_value('description', $coc->getDescription())) . '</div></div>';
        return $result;
    }

}

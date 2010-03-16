<?php 
define('DISPLAY', 0);
define('STRUCTURE', 1);
define('NAVIGATION', 2);
define('ALT_TO_TEXT', 3);

global $_custom_head, $onload;
    
$_custom_head = "<script language=\"JavaScript\" src=\"jscripts/TILE.js\" type=\"text/javascript\"></script>";
$onload = "setPreviewFace(); setPreviewSize(); setPreviewColours();";

require(AT_INCLUDE_PATH.'header.inc.php'); 
?>

<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" name="form" enctype="multipart/form-data">
<?php
    $pref_next = $this->pref_next;
    $pref = $this->pref_wiz[$pref_next];
    if ($pref != null) {
        switch ($pref) {
            case DISPLAY:
                include_once('../display_settings.inc.php');
                break;
            case STRUCTURE:
                echo "structural stuff";
                break;
            case NAVIGATION:
                include_once('../control_settings.inc.php');
                break;
            case ALT_TO_TEXT:
                include_once('../alt_to_text.inc.php');
                break;
        }
        foreach ($this->pref_wiz as $pref => $template) { 
            echo '<input type="hidden" name="pref_wiz[]" value="'.$template.'" />';
        }
        $pref_next++;
        echo '<input type="hidden" value="'.$pref_next.'" name="pref_next" id="pref_next" />';
    } else {
        $savant->display('users/pref_wizard/initialize.tmpl.php');
    }
?>
    <input type="submit" value="Next" name="next" id="next" />
</form>

<?php 
    require(AT_INCLUDE_PATH.'footer.inc.php'); 
?>
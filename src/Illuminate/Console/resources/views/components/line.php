<div class="mx-2 mb-1 mt-<?php 
echo $margin_top;
?>">
    <span class="px-1 bg-<?php 
echo $bg_color;
?> text-<?php 
echo $fg_color;
?> uppercase"><?php 
echo $title;
?></span>
    <span class="<?php 
if ($title) {
    echo 'ml-1';
}
?>">
        <?php 
echo htmlspecialchars((string) $content);
?>
    </span>
</div>

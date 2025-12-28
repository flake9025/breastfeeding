<!-- JavaScript avec version pour éviter le cache -->
	<?php 
	$version = defined('ASSET_VERSION') ? ASSET_VERSION : time();
	?>

	<?php if (isset($additionalJS)): ?>
		<?php foreach ($additionalJS as $js): ?>
			<script src="<?= $js ?>?v=<?= $version ?>"></script>
		<?php endforeach; ?>
	<?php endif; ?>
	<!-- Version visible pour debug -->
	<div style="position: fixed; bottom: 5px; left: 5px; font-size: 10px; color: rgba(0,0,0,0.3); z-index: 9999;">
		v<?= $version ?? 'dev' ?>
	</div>
</body>
</html>

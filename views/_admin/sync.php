<div id="fuel_main_content_inner">
	<p class="instructions">Here you can sync remote assets and data with your local version.</p>
	<?=$form?>

	<br />
	<div id="sync_loader" class="loader hidden"></div>
	<div id="sync_log">
	</div>

	<script type="text/javascript">
	//<![CDATA[
		$(function(){
			var params = {
				beforeSubmit : function(){
					$('#sync_loader').show();
				},

				success : function(html){
					$('#sync_log').html(html);
					$('#sync_loader').hide();
					$('.submit').attr('disabled', false).removeClass('disabled');
				}
			}
			$('#form').ajaxForm(params)

		})
	//]]>
	</script>
</div>
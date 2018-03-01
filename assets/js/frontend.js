(function($){
	$( '.order-details-toggle' ).on(
		'click', function(){
			$( '#order-details' ).slideToggle();
		}
	);
	
	var cvcTooltip = $(
		'<div id="find-your-cvc">' +
			'<a class="find-your-cvc-hide close">' + woocommerceFunnels.cvcInstructions.closeText + '</a>' +
			'<p>' + woocommerceFunnels.cvcInstructions.text + '</p>' +
			'<img src="' + woocommerceFunnels.cvcInstructions.imageUrl + '" />' +
		'</div>'
	);
	
	var cvcTooltipButton = $(
		'<a class="find-your-cvc-show open">' + woocommerceFunnels.cvcInstructions.buttonText + '</a>'
	);
	
	cvcTooltipButton.on('click', function(){
		cvcTooltip.addClass('tooltip-show');
	});

	cvcTooltip.on('click', '.find-your-cvc-hide', function(){
		cvcTooltip.removeClass('tooltip-show');
	});
	
	$(document.body).on('updated_checkout wc-credit-card-form-init', function(){
		$('#stripe-cvc-element')
			.after( cvcTooltip )
			.after( cvcTooltipButton );
	} );

})( jQuery );

<script>
	window.chatwootSettings = {
		locale: navigator.language.replace('-', '_')
	};
	(function(d,t) {
	var BASE_URL='https://chat.captcha.eu';
	var g=d.createElement(t),s=d.getElementsByTagName(t)[0];
	g.src = BASE_URL + '/packs/js/sdk.js';
	g.defer = true;
	g.async = true;
	s.parentNode.insertBefore(g,s);
	g.onload=function(){
		window.chatwootSDK.run({
			websiteToken: 'zdXfmKEQxaWLEoF2FZ4h39H3',
			baseUrl: BASE_URL
		})
	}
	})(document, 'script');
</script>
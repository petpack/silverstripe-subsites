function hideSubsiteMenu() {
	form = jQuery('#SubsiteActions');
	
	if (form.hasClass('active')) {
		
		textbox = jQuery('#SubsiteActions #SubsiteSearch');
		textbox.css({
			background: 'none',
			border: 'none',
		});
		
		form.animate({height: '20px'},333);
		form.removeClass('active');
		
	}
	
}

jQuery(window).click(function(evt) {
	//if we clicked on anything that isn't in the subsite menu, hide it
	if (jQuery(evt.target).parents('#SubsiteActions').length == 0) {
		hideSubsiteMenu();
	}
});

Behaviour.register({
	'#SubsiteActions select' : {
		onchange: function() {
			if($('Form_AddPageOptionsForm_SubsiteID')) {
				$('Form_AddPageOptionsForm_SubsiteID').value = this.value;
			}
			var request = new Ajax.Request(SiteTreeHandlers.controller_url + '/changesubsite?SubsiteID=' + this.value + '&ajax=1', {
				onSuccess: function(response) {
					if ($('sitetree')) {
						$('sitetree').innerHTML = response.responseText;
						SiteTree.applyTo($('sitetree'));
						$('sitetree').getTreeNodeByIdx(0).onselect();
						$('siteTreeFilterList').reapplyIfNeeded();
					}
				},
				
				onFailure: function(response) {
					errorMessage('Could not change subsite', response);
				}
			});
		}
	},
	
	'#SubsiteActions #SubsiteSearch': {
		onfocus: function() {
			textbox = jQuery('#SubsiteActions #SubsiteSearch');
			textbox.css({
				background: '#fff',
				border: '1px inset #aaa',
			});
			textbox[0].select();
			form = jQuery('#SubsiteActions');
			form.animate({height: '540px'},333);
			form.addClass('active');
			//evt = jQuery("body").click(hideSubsiteMenu);
			
		}
	},
	
	'ul#SubsitesSelect li a': {
		onclick: function() {
			itm=jQuery(this);
			
			textbox = jQuery('#SubsiteActions #SubsiteSearch');
			textbox.val(itm.text());
			
			jQuery('ul#SubsitesSelect li a').removeClass('selected');
			itm.addClass('selected');
			
			id = itm.attr('data-value');
			
			jQuery('input#SubsiteID').val(id);
			
			console.log("Switching to subsite " + id + " (" + itm.text() + ")");
			
			//TODO: set ID value in hidden input and do AJAX call.
			
			
			hideSubsiteMenu();
		}
	},
	
	//DM: I don't think this "new subsite" item exists anywhere. was just #SubsiteActions a 
	'#SubsiteActions a.new-subsite' : {
		onclick: function() {
			var subsiteName = prompt('Enter the name of the new site','');
			if(subsiteName && subsiteName != '') {
				var request = new Ajax.Request(this.href + '?Name=' + encodeURIComponent(subsiteName) + '&ajax=1', {
					onSuccess: function(response) {
						var origSelect = $('SubsitesSelect');
						var div = document.createElement('div');
						div.innerHTML = response.responseText;
						var newSelect = div.firstChild;
						
						while(origSelect.length > 0)
							origSelect.remove(0);
						
						for(var j = 0; j < newSelect.length; j++) {
							var opt = newSelect.options.item(j).cloneNode(true);
							var newOption = document.createElement('option');
							
							/*if(opt.text)
								newOption.text = opt.text;*/
							if(opt.firstChild)
								newOption.text = opt.firstChild.nodeValue;
							
							newOption.value = opt.value;
							try {
								origSelect.add(newOption, null);
							} catch(ex) {
								origSelect.add(newOption);
							}
						}
						
						statusMessage('Created ' + subsiteName, 'good');
					},
					onFailure: function(response) {
						errorMessage('Could not create new subsite', response);
					}
				});
			}
			
			return false;
		}
	},
	
	// Subsite tab of Group editor
	'#Form_EditForm_AccessAllSubsites' : {
		initialize: function () {
			this.showHideSubsiteList();
			var i=0,items=this.getElementsByTagName('input');
			for(i=0;i<items.length;i++) {
				items[i].onchange = this.showHideSubsiteList;
			}
		},
		
		showHideSubsiteList : function () {
			$('Form_EditForm_Subsites').parentNode.style.display = 
				Form.Element.getValue($('Form_EditForm').AccessAllSubsites)==1 ? 'none' : '';
		}
	}
});

// Add an item to fieldsToIgnore
Behaviour.register({
	'#Form_EditForm' : {
		initialize: function () {
			this.changeDetection_fieldsToIgnore.IsSubsite = true;
		}
	}	
});

fitToParent('ResultTable_holder');

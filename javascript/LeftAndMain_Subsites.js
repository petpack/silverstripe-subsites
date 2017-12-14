
function showSubsiteMenu() {
	//select all text
	jQuery('#SubsiteActions #SubsiteSearch')[0].select();
	
	form = jQuery('#SubsiteActions');
	form.animate({height: '540px'},333);
	form.addClass('active');
	applySubsiteFilters();	//reset the list to show 'all'
}


function hideSubsiteMenu() {
	form = jQuery('#SubsiteActions');
	
	if (form.hasClass('active')) {
		form.animate({height: '20px'},333);
		form.removeClass('active');
		jQuery('#SubsiteActions #SubsiteSearch').val(jQuery('input#SubsiteID').attr('data-title'));
		return true;
	} else return false;
	
}

function toggleSubsiteMenu() {
	if (!hideSubsiteMenu())
		showSubsiteMenu();
}

/**
 * Change the subsite to the one specified. This involves an ajax call.
 * @param subsiteid	int	 new subsite ID
 */
function changeSubsite(subsiteid) {
	if (!subsiteid)
		return false;
	
	if($('Form_AddPageOptionsForm_SubsiteID')) {
		$('Form_AddPageOptionsForm_SubsiteID').value = subsiteid;
	}
	showSubsiteSpinner();
	
	var request = new Ajax.Request(SiteTreeHandlers.controller_url + '/changesubsite?SubsiteID=' + subsiteid + '&ajax=1', {
		onSuccess: function(response) {
			hideSubsiteSpinner();
			if ($('sitetree')) {
				$('sitetree').innerHTML = response.responseText;
				SiteTree.applyTo($('sitetree'));
				$('sitetree').getTreeNodeByIdx(0).onselect();
				$('siteTreeFilterList').reapplyIfNeeded();
			}
			//fire the change event for the #SubsiteID hidden input.
			//	if you want to run custom code (e.g to refresh) on subsite change,
			//	bind an event to $('#SubsiteActions #SubsiteID').change();
			jQuery('#SubsiteActions #SubsiteID').trigger('change');
		},
		
		onFailure: function(response) {
			jQuery("#SubsiteActions .icons .fa-spinner").remove();
			
			errorMessage('Could not change subsite', response);
		}
	});
}

jQuery(window).click(function(evt) {
	if (jQuery(evt.target).parents('#SubsiteActions').length == 0) {
		//element is not a child of the subsite menu - hide the menu.
		hideSubsiteMenu();
	}
});

/**
 * Applies subsite filters. This is how you reset the subsite list to a 'show all' state
 * @returns
 */
function applySubsiteFilters() {
	
	var activeOnly = jQuery("input#active-only").is(':checked');
	
	jQuery('ul#SubsitesSelect li a').each(function() {
		ele = jQuery(this);
		var show = true;
		
		if (activeOnly) {
			if (ele.attr('data-active') != "1")
				show = false;
		}
		
		if (show)
			ele.parent().show();
		else
			ele.parent().hide();
		
	});
}

//var used to store timeout for search
var searchTimeout = false;

function doSubsiteSearch() {
	var searchTerm = jQuery('#SubsiteActions #SubsiteSearch').val().toLowerCase();
	applySubsiteFilters();
	jQuery('ul#SubsitesSelect li a').each(function() {
		ele = jQuery(this);
		if (ele.parent().is(':visible')) {
			if (ele.text().toLowerCase().indexOf(searchTerm) != -1) {
				ele.parent().show();
			} else
				ele.parent().hide();
		}
	});
	
	hideSubsiteSpinner();
	
}

function showSubsiteSpinner() {
	if (jQuery("#SubsiteActions .icons .fa-spinner").length) return false;	//there can be only one!
	
	spinner = jQuery('<i class="fa fa-spin fa-spinner" ></i>');
	jQuery('#SubsiteActions .icons').prepend(spinner);
}

function hideSubsiteSpinner() {
	jQuery("#SubsiteActions .icons .fa-spinner").remove();
}

Behaviour.register({
	
	//DM: @TODO: this function is now defunct, remove it:
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
			showSubsiteMenu();
		},
		onkeyup: function() {
			showSubsiteSpinner();
			
			//we don't run the search immediately, we wait 333ms for another keystroke
			if (searchTimeout) window.clearTimeout(searchTimeout);
			searchTimeout = window.setTimeout(doSubsiteSearch,666);
		}
	},
	'#SubsiteActions .icons .caret': {
		onclick: function() {
			toggleSubsiteMenu();
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
			
			ele = jQuery('input#SubsiteID')
			ele.val(id);
			ele.attr('data-title',itm.text());
			
			changeSubsite(id);
			
			hideSubsiteMenu();
		}
	},
	
	"input#active-only": {
		onchange: function() {
			applySubsiteFilters();
			if (jQuery('#SubsiteActions #SubsiteSearch').val() != jQuery('input#SubsiteID').attr('data-title')) {
				doSubsiteSearch();
			}
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

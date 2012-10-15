
// 1. Initialization

$(window).load(resizeJobs);
$(window).resize(resizeJobs);
function resizeJobs() {
	$('#main').width($(window).width()*0.71);
}

var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']; // why does every program need this?

function loadData() { // loads json array of values into respective content areas
	$.ajax({url: location.href,
		type: 'POST',
		data: 'action=load',
		timeout: 20000,
		success: function(data) {
			if(data) {
				data = eval('(' + data + ')');
				var active = {Overdue:[], Today:[], Tomorrow:[], ThisWeek:[], NextWeek:[], Later:[]};
				var completed = [];
				var prospective = [];
				var dates = [];
				// Step 1: compile + organize
				$.each(data.active, function(i, val) {
					var now = new Date();
					now = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 0, 0, 0); // sets time to midnight
					var unix = Math.floor(now.getTime() / 1000);
					var due = new Date(val.due * 1000);
					var diff = Math.ceil((Math.floor(due.getTime() / 1000) - unix) / 86400); // Difference, in days, rounded up, between now and due date
					if(diff < 1) { // Sort according to number of days left; here: overdue; below: today / tomorrow / this week / next week / later
						active.Overdue.push(val);
					} else {
						switch(diff) {
							case 1:
								active.Today.push(val);
								break;
							case 2:
								active.Tomorrow.push(val);
								break;
							case 3: case 4: case 5: case 6: case 7:
								if(due.getDay() < now.getDay()) { // ex, push to next week if today == Fri and due == Mon
									active.NextWeek.push(val);
								} else {
									active.ThisWeek.push(val);
								}
								break;
							case 8: case 9: case 10: case 11: case 12: case 13: case 14:
								if(due.getDay() < now.getDay()) { // ^^
									active.Later.push(val);
								} else {
									active.NextWeek.push(val);
								}
								break;
							default : // later
								active.Later.push(val);
								break;
						}
					}
				});
				$.each(data.completed, function(i, val) {
					completed.push(val);
				});
				$.each(data.prospective, function(i, val) {
					prospective.push(val);
				});
				$.each(data.dates, function(i, val) {
					dates.push(val);
				});
				// Step 2: generate output
				var activehtml = '';
				if(active.Overdue.length > 0) {
					activehtml += '<h1><span class="due-text">Due</span>Overdue</h1><section class="jobs overdue">';
					$.each(active.Overdue, function(i, val) {
						activehtml += activeFormat(i, val);
					});
					activehtml += '</section>';
				}
				if(active.Today.length > 0) {
					activehtml += '<h1><span class="due-text">Due</span>Today</h1><section class="jobs today">';
					$.each(active.Today, function(i, val) {
						activehtml += activeFormat(i, val);
					});
					activehtml += '</section>';
				}
				if(active.Tomorrow.length > 0) {
					activehtml += '<h1><span class="due-text">Due</span>Tomorrow</h1><section class="jobs tomorrow">';
					$.each(active.Tomorrow, function(i, val) {
						activehtml += activeFormat(i, val);
					});
					activehtml += '</section>';
				}
				if(active.ThisWeek.length > 0) {
					activehtml += '<h1><span class="due-text">Due</span>This Week</h1><section class="jobs this-week">';
					$.each(active.ThisWeek, function(i, val) {
						activehtml += activeFormat(i, val);
					});
					activehtml += '</section>';
				}
				if(active.NextWeek.length > 0) {
					activehtml += '<h1><span class="due-text">Due</span>Next Week</h1><section class="jobs next-week">';
					$.each(active.NextWeek, function(i, val) {
						activehtml += activeFormat(i, val);
					});
					activehtml += '</section>';
				}
				if(active.Later.length > 0) {
					activehtml += '<h1><span class="due-text">Due</span>Later</h1><section class="jobs later">';
					$.each(active.Later, function(i, val) {
						activehtml += activeFormat(i, val);
					});
					activehtml += '</section>';
				}
				var completedhtml = '<section class="jobs completed">';
				$.each(completed, function(i, val) {
					completedhtml += completedFormat(i, val);
				});	
				completedhtml += '</section>';
				var prospectivehtml = '';
				$.each(prospective, function(i, val) {
					prospectivehtml += prospectiveFormat(i, val);
				});
				var dateshtml = '';
				$.each(dates, function(i, val) {
					dateshtml += datesFormat(i, val);
				});	
				// Step 3: display output
				$('#jobs-active').html(activehtml);
				$('#jobs-completed').html(completedhtml);
				$('#jobs-prospective').html(prospectivehtml);
				$('#dates').html(dateshtml);
			}
		}
	});
}
loadData();

// 2. Listeners

$('#tabs a').click(function() {
	var id = $(this).attr('id');
	$('#jobs > div').addClass('selected').not('#jobs-'+id).removeClass('selected');
	$(this).addClass('selected');
	$('#tabs a').not($(this)).removeClass('selected');
	var topHeight = ($('#jobs-active:visible').length > 0) ? 31 : 8;
	$('#jobs-prospective').animate({marginTop: topHeight}, 300);
	return false;
});
$(document).on('click', 'a.change', function() {
	var id = $(this).attr('data-id');
    $('#date-'+id).datepicker({minDate: 0, onSelect: function(dateText, inst) {
    	$.ajax({url: location.href,
		type: 'POST',
		data: 'action=update&id='+id+'&due='+encodeURIComponent(dateText),
		timeout: 10000,
		success: function(data) {
			if(data == 'Success') {
				update();
			}
		}
		});
    }}).focus();
	return false;
});
$(document).on('click', 'a.show-group', function() {
	$('article:hidden').stop(true).slideDown(300); // could be better, but difficult with the active / completed tabs hiding some articles already
	$('#jobs article, #jobs-completed article').not('.'+$(this).attr('data-id')).stop(true).slideUp(300);
	return false;
});
$(document).on('click', 'article h1, article span.group_name, article span.description', function() {
	var width = $(this).closest('section').is('#jobs-prospective') ? $(this).width() : $(this).width()*1.25;
	var name = $(this).text();
	var type = $(this).attr('class');
	var id = $(this).attr('data-id');
	if($(this).find('input').length == 0) {
		$(this).html('<input data-id="'+id+'" name="'+type+'" style="width:'+width+'px" value="'+name+'" />');
		$(this).find('input').focus().select();
	}
});
$(document).on('blur', 'input', function() {
	var newJob = $(this).closest('section').is('.new-job');
	setTimeout(function() {
		if($('input:focus').length == 0 && newJob === false) update(); // Don't re-load if multiple things are being updated.
	}, 300);
});
$(document).on('click', 'section:not(.completed) a.check', function() {
	var id = $(this).attr('data-id');
	$.ajax({url: location.href,
		type: 'POST',
		data: 'action=complete&id='+id,
		timeout: 10000,
		success: function(data) {
			if(data == 'Success') {
				$('#'+id).animate({backgroundColor: '#27cd3b', marginLeft: 400, opacity: 0}, 300).slideUp(300, function() {
					$(this).hide();
					loadData();
				});
			}
		}
	});
	return false;
});
$(document).on('click', 'section.completed a.check', function() {
	var id = $(this).attr('data-id');
	$.ajax({url: location.href,
		type: 'POST',
		data: 'action=uncomplete&id='+id,
		timeout: 10000,
		success: function(data) {
			if(data == 'Success') {
				$('#'+id).animate({marginRight: 400, opacity: 0}, 300).slideUp(300, function() {
					$(this).hide();
					loadData();
				});
			}
		}
	});
	return false;
});
$(document).on('click', 'section a.del', function() {
	var id = $(this).attr('data-id');
	$.ajax({url: location.href,
		type: 'POST',
		data: 'action=delete&id='+id,
		timeout: 10000,
		success: function(data) {
			if(data == 'Success') {
				$('#'+id).animate({backgroundColor: '#d13f34', marginRight: 400, opacity: 0}, 300).slideUp(300, function() {
					$(this).hide();
					loadData();
				});
			}
		}
	});
	return false;
});
$(document).on('click', 'section a.move', function() {
	var id = $(this).attr('data-id');
	var now = Math.round(+new Date()/1000);
	$.ajax({url: location.href,
		type: 'POST',
		data: 'action=move&id='+id+'&due='+now,
		timeout: 10000,
		success: function(data) {
			if(data) {
				$('#'+id).animate({backgroundColor: '#27cd3b', marginRight: 400, opacity: 0}, 300).slideUp(300, function() {
					$(this).hide();
					data = eval('('+data+')');
					due = new Date(data.due * 1000);
					$('#jobs > div:not(#jobs-active)').removeClass('selected');
					$('#jobs-active').addClass('selected').prepend('<section class="jobs new-job">'+activeFormat(0, data)+'</section>');
					$('#jobs-active').find('section.new-job > article:eq(0)').height(0).animate({height: 45}, 300);
				});
			}
		}
	});
	return false;
})
$('body').click(function() { // undo filter above
	$('article:hidden').slideDown(300);
})
$(document).on('click, focus', '#dataset input.editable[readonly=readonly]', function() {
	$(this).removeAttr('readonly').focus();
});
$(document).on('blur', '#dataset input', function() {
	setTimeout(function() {
		if($('input:focus').length == 0) {
			formSubmit();
		}
	}, 100);
});
$('#jobs, #extras').keydown(function(e) {
	if(e.keyCode == 13 && $('section.new-job').length === 0) update();
});
$('a.new').click(function() {
	$("html, body").animate({scrollTop: 0}, 200);
	$.ajax({url: location.href,
		type: 'POST',
		data: 'action=add',
		timeout: 10000,
		success: function(data) {
			if(data) {
				data = eval('('+data+')');
				due = new Date(data.due * 1000);
				$('#jobs > div:not(#jobs-active)').removeClass('selected');
				$('#jobs-active').addClass('selected').prepend('<section class="jobs new-job">'+activeFormat(0, data)+'</section>');
				$('#jobs-active').find('section.new-job > article:eq(0)').height(0).animate({height: 45}, 300);
			}
		}
	});
	return false;
});
$('a.new-prospective').click(function() {
	$("html, body").animate({scrollTop: 0}, 200);
	$.ajax({url: location.href,
		type: 'POST',
		data: 'action=add&type=prospective',
		timeout: 10000,
		success: function(data) {
			if(data) {
				data = eval('('+data+')');
				due = new Date(data.due * 1000);
				$('#jobs-prospective').prepend(prospectiveFormat(0, data)).find('article:eq(0)').height(0).animate({height: 45}, 300);
			}
		}
	});
	return false;
});
$('a.new-date').click(function() {
	$("html, body").animate({scrollTop: 0}, 200);
	$.ajax({url: location.href,
		type: 'POST',
		data: 'action=add&type=date',
		timeout: 10000,
		success: function(data) {
			if(data) {
				data = eval('('+data+')');
				due = new Date(data.due * 1000);
				$('#dates').prepend(datesFormat(0, data)).find('article:eq(0)').height(0).animate({height: 24}, 300);
			}
		}
	});
	return false;
});

// 3. Other functions / models

function update() {
	var input = $('input:visible');
	$.each(input, function(i, o) {
		var val = encodeURIComponent($(this).val().replace(/'/g, "’"));
		var type = $(this).attr('name');
		var id = $(this).attr('data-id');
		$.ajax({url: location.href,
			type: 'POST',
			data: 'action=update&id='+id+'&'+type+'='+val,
			timeout: 20000, // 10 sec timeout
			success: function(data) {
				if(data == 'Success') {
					loadData();
				}
			}
		});
	});
}

function slugParse(input) {
	return input.toLowerCase().replace(/ /g,'-').replace(/[^\w-]+/g,'');
}

function activeFormat(i, val) {
	var gslug = val.group_name ? slugParse(val.group_name) : '';
	var due = new Date(val.due * 1000);
	var title = '';
	if(i == 0) title = '<h2>Complete?</h2>';
	return '<article id="'+val.ID+'" class="job '+gslug+'"><a href="#" class="del" data-id="'+val.ID+'">×</a><div class="left"><span class="group_name" data-id="'+val.ID+'">'+val.group_name+'</span><a class="show-group" href="#" data-id="'+gslug+'">Show</a><div class="details"><h1 class="name" data-id="'+val.ID+'">'+val.name+'</h1><span class="description" data-id="'+val.ID+'">'+val.description+'</span></div></div><div class="right"><div class="completed">'+title+'<a class="check" href="#" data-id="'+val.ID+'">&#10003;</a></div><div class="due">'+months[due.getMonth()]+' '+due.getDate()+'<input id="date-'+val.ID+'" class="date" type="text" /><a class="change" href="#" data-id="'+val.ID+'">Change</a></div></div></article>';
}

function completedFormat(i, val) {
	var gslug = val.group_name ? slugParse(val.group_name) : '';
	var due = new Date(val.due * 1000);
	var finished = new Date(val.finished * 1000);
	var titles = ['', '', ''];
	if(i == 0) titles = ['<h2>Complete?</h2>', '<h2>Due</h2>', '<h2>Finished</h2>'];
	return '<article id="'+val.ID+'" class="job '+gslug+'"><a href="#" class="del" data-id="'+val.ID+'">×</a><div class="left"><span class="group_name" data-id="'+val.ID+'">'+val.group_name+'</span><a class="show-group" href="#" data-id="'+gslug+'">Show</a><div class="details"><h1 class="name" data-id="'+val.ID+'">'+val.name+'</h1><span class="description" data-id="'+val.ID+'">'+val.description+'</span></div></div><div class="right"><div class="completed">'+titles[0]+'<a class="check marked" href="#" data-id="'+val.ID+'">&#10003;</a></div><div class="due">'+titles[1]+months[due.getMonth()]+' '+due.getDate()+'<input id="'+val.ID+'" class="date" type="text" /></div><div class="finished">'+titles[2]+months[finished.getMonth()]+' '+finished.getDate()+'</div></div></article>';
}

function prospectiveFormat(i, val) {
	var titles = '';
	if(i == 0) title = '<h2>Move</h2>';
	return '<article id="'+val.ID+'" class="prospective"><a href="#" class="del" data-id="'+val.ID+'">×</a><h1 class="name" data-id="'+val.ID+'">'+val.name+'</h1><span class="description" data-id="'+val.ID+'">'+val.description+'</span><div class="right"><div class="actions">'+title+'<a href="#" class="move" data-id="'+val.ID+'">&larr;</a></div></div></article>';
}

function datesFormat(i, val) {
	return '<article id="'+val.ID+'" class="date"><a href="#" class="del" data-id="'+val.ID+'">×</a><h1 class="name" data-id="'+val.ID+'">'+val.name+'</h1><span class="description" data-id="'+val.ID+'">'+val.description+'</span></article>';
}


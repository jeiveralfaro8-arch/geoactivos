function toggleAll(on){
  document.querySelectorAll('.perm-check').forEach(function(cb){
    cb.checked = !!on;
  });
}

function toggleGroup(group, on){
  document.querySelectorAll('[data-group="'+group+'"] .perm-check').forEach(function(cb){
    cb.checked = !!on;
  });
}

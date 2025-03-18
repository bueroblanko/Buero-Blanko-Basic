document.addEventListener('DOMContentLoaded', function() {
  var jahrElement = document.getElementById('jahr');
  var aktuellesJahr = new Date().getFullYear();
  jahrElement.innerHTML = aktuellesJahr;
});
jQuery(function($){

    $('.dpdfg_filtergrid_0 .entry-meta span a').removeAttr('href');
    $('.dp-dfg-meta.entry-meta span').removeAttr('href');

});

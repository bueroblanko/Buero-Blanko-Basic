// Aktuelles Jahr in ein Element mit der ID "jahr" schreiben (z. B. im Footer).
document.addEventListener('DOMContentLoaded', function() {
  var jahrElement = document.getElementById('jahr');
  if (jahrElement) {
    jahrElement.innerHTML = new Date().getFullYear();
  }
});

// Divi Filtergrid: Links in den Meta-Angaben entfernen.
// TODO: Sonderlogik für eine einzelne Seite, gehört ins Child-Theme dieser Seite.
jQuery(function($){

    $('.dpdfg_filtergrid_0 .entry-meta span a').removeAttr('href');
    $('.dp-dfg-meta.entry-meta span').removeAttr('href');

});

<?php

declare(strict_types=1);

/* Swiss German's chrome: only the words whose spelling differs from `de.php`,
   which is read for every other, so this file is the ß words written ss. */
return [
  'install.refused.unproven' => 'Der Abruf des Datenordners von aussen hat mit {status} geantwortet. Nur eine Abweisung, 403 oder 404, zeigt, dass er geschützt ist – darum wurde nichts gespeichert.',
  'home.grants_sub' => 'Wer von aussen schreibt',
  'locale.default_says' => 'Das ändert die Adresse jeder Sammlung: sie heisst dann /feed/{tag}/…, und neue Datensätze entstehen auf {tag}. Was schon steht, behält seine Adresse.',
  'backups.restore_explain' => 'Die Installation geht auf diesen Stand zurück. Was seitdem geschrieben wurde, bleibt als Backup „vor der Wiederherstellung“ erhalten, nicht in der laufenden Installation, und alle melden sich neu an. Wer hinein darf, bleibt wie jetzt: Ein seitdem entferntes Konto oder ein gesperrter Schlüssel bleibt draussen, und deines bleibt dir.',
  'export.size' => 'Grösse',
  'update.refused_again' => 'Skemik {version} liess sich noch nicht automatisch einspielen: {reason}. Jede Stunde wird es erneut versucht, es genügt also, das zu beheben; oder die Version von Hand über diese kopieren, config.php bleibt unberührt, und der Bestand zieht beim ersten Aufruf um.',
  'grant.none' => 'Keine Vollmacht – nichts schreibt hier von aussen, und gelesen wird nur, was veröffentlicht ist.',
  'grant.others' => 'Derselbe Schlüssel darf ausserdem:',
  'grant.secret' => 'Der Schlüssel für {name}, nur jetzt sichtbar. Nach dem Schliessen ist er weg; ein verlorener Schlüssel wird neu ausgestellt.',
  'grant.reissue_hint' => 'Ein neuer Schlüssel unter diesem Namen, mit dem Angekreuzten und nicht mehr. Der bisherige gilt bis zu seinem Datum weiter, ausser er wird hier gesperrt.',
  'media.lost' => 'Die Datei kam nicht an. Grösser als {limit} nimmt dieser Server nicht an.',
  'media.none' => 'Es kam keine Datei an. Grösser als {limit} nimmt dieser Server nicht an.',
  'media.too_large' => '„{name}“ ist grösser als {limit} – mehr nimmt dieser Server nicht an.',
];

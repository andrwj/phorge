CREATE TABLE {$NAMESPACE}_search.search_applicationngram (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  objectPHID VARBINARY(64) NOT NULL,
  ngram BINARY(12) NOT NULL,
  UNIQUE KEY key_ngram (ngram, objectPHID),
  KEY key_object (objectPHID)
) ENGINE=InnoDB;

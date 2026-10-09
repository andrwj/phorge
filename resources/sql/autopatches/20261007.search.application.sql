CREATE TABLE {$NAMESPACE}_search.search_applicationdocument (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  objectPHID VARBINARY(64) NOT NULL,
  objectType VARCHAR(4) NOT NULL COLLATE utf8mb4_bin,
  title LONGTEXT NOT NULL COLLATE utf8mb4_bin,
  body LONGTEXT NOT NULL COLLATE utf8mb4_bin,
  comments LONGTEXT NOT NULL COLLATE utf8mb4_bin,
  corpus LONGTEXT NOT NULL COLLATE utf8mb4_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY key_object (objectPHID),
  KEY key_type (objectType)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

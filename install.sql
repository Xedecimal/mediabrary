/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

# Dumping structure for table mediabrary.codec
DROP TABLE IF EXISTS `codec`;
CREATE TABLE IF NOT EXISTS `codec` (
  `cod_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cod_path` varchar(255) NOT NULL,
  `cod_name` varchar(255) NOT NULL,
  `cod_value` varchar(255) NOT NULL,
  PRIMARY KEY (`cod_id`),
  UNIQUE KEY `cod_path_cod_name` (`cod_path`,`cod_name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

# Data exporting was unselected.


# Dumping structure for table mediabrary.movie
DROP TABLE IF EXISTS `movie`;
CREATE TABLE IF NOT EXISTS `movie` (
  `mov_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mov_clean` tinyint(1) unsigned NOT NULL,
  `mov_title` varchar(255) NOT NULL,
  `mov_date` date NOT NULL,
  `mov_tmdbid` int(10) unsigned NOT NULL,
  PRIMARY KEY (`mov_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

# Data exporting was unselected.


# Dumping structure for table mediabrary.movie_category
DROP TABLE IF EXISTS `movie_category`;
CREATE TABLE IF NOT EXISTS `movie_category` (
  `cat_id` int(10) NOT NULL AUTO_INCREMENT,
  `cat_movie` int(10) unsigned NOT NULL,
  `cat_name` varchar(255) NOT NULL,
  PRIMARY KEY (`cat_id`),
  UNIQUE KEY `cat_movie` (`cat_movie`),
  CONSTRAINT `FK_movie_category_movie` FOREIGN KEY (`cat_movie`) REFERENCES `movie` (`mov_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

# Data exporting was unselected.


# Dumping structure for table mediabrary.movie_date
DROP TABLE IF EXISTS `movie_date`;
CREATE TABLE IF NOT EXISTS `movie_date` (
  `md_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `md_movie` int(10) unsigned NOT NULL,
  `md_name` varchar(255) NOT NULL,
  `md_value` date NOT NULL,
  PRIMARY KEY (`md_id`),
  UNIQUE KEY `md_movie_md_name` (`md_movie`,`md_name`),
  CONSTRAINT `FK_movie_date_movie` FOREIGN KEY (`md_movie`) REFERENCES `movie` (`mov_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

# Data exporting was unselected.


# Dumping structure for table mediabrary.movie_detail
DROP TABLE IF EXISTS `movie_detail`;
CREATE TABLE IF NOT EXISTS `movie_detail` (
  `md_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `md_movie` int(10) unsigned NOT NULL DEFAULT '0',
  `md_name` varchar(255) NOT NULL,
  `md_value` mediumtext NOT NULL,
  PRIMARY KEY (`md_id`),
  UNIQUE KEY `md_movie_md_name` (`md_movie`,`md_name`),
  CONSTRAINT `FK_movie_detail_movie` FOREIGN KEY (`md_movie`) REFERENCES `movie` (`mov_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

# Data exporting was unselected.


# Dumping structure for table mediabrary.movie_float
DROP TABLE IF EXISTS `movie_float`;
CREATE TABLE IF NOT EXISTS `movie_float` (
  `md_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `md_movie` int(10) unsigned NOT NULL,
  `md_name` varchar(255) NOT NULL,
  `md_value` float NOT NULL,
  PRIMARY KEY (`md_id`),
  UNIQUE KEY `md_movie_md_name` (`md_movie`,`md_name`),
  CONSTRAINT `FK_movie_float_movie` FOREIGN KEY (`md_movie`) REFERENCES `movie` (`mov_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

# Data exporting was unselected.


# Dumping structure for table mediabrary.movie_path
DROP TABLE IF EXISTS `movie_path`;
CREATE TABLE IF NOT EXISTS `movie_path` (
  `mp_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mp_movie` int(10) unsigned NOT NULL,
  `mp_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`mp_id`),
  UNIQUE KEY `mp_path` (`mp_path`),
  KEY `FK_movie_path_movie` (`mp_movie`),
  CONSTRAINT `FK_movie_path_movie` FOREIGN KEY (`mp_movie`) REFERENCES `movie` (`mov_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

# Data exporting was unselected.


# Dumping structure for table mediabrary.rate
DROP TABLE IF EXISTS `rate`;
CREATE TABLE IF NOT EXISTS `rate` (
  `rate_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rate_for` int(10) unsigned NOT NULL,
  `rate_from` int(10) unsigned NOT NULL,
  `rate_amount` tinyint(1) unsigned NOT NULL,
  PRIMARY KEY (`rate_id`),
  UNIQUE KEY `rate_for` (`rate_for`),
  CONSTRAINT `FK_rate_movie` FOREIGN KEY (`rate_for`) REFERENCES `movie` (`mov_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

# Data exporting was unselected.
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;

# --------------------------------------------------------
# Host:                         127.0.0.1
# Server version:               5.1.31-community-log
# Server OS:                    Win32
# HeidiSQL version:             6.0.0.3627
# Date/time:                    2011-01-01 23:07:00
# --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

# Dumping database structure for mediabrary
CREATE DATABASE IF NOT EXISTS `mediabrary` /*!40100 DEFAULT CHARACTER SET latin1 */;
USE `mediabrary`;


# Dumping structure for table mediabrary.codec
CREATE TABLE IF NOT EXISTS `codec` (
  `cod_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cod_path` varchar(255) NOT NULL,
  `cod_name` varchar(255) NOT NULL,
  `cod_value` varchar(255) NOT NULL,
  PRIMARY KEY (`cod_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

# Data exporting was unselected.


# Dumping structure for table mediabrary.movie
CREATE TABLE IF NOT EXISTS `movie` (
  `mov_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mov_path` varchar(255) NOT NULL,
  `mov_clean` tinyint(1) unsigned NOT NULL,
  `mov_title` varchar(255) NOT NULL,
  `mov_date` date NOT NULL,
  `mov_tmdbid` int(10) unsigned NOT NULL,
  PRIMARY KEY (`mov_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

# Data exporting was unselected.


# Dumping structure for table mediabrary.movie_category
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
CREATE TABLE IF NOT EXISTS `movie_date` (
  `md_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `md_movie` int(10) unsigned NOT NULL DEFAULT '0',
  `md_name` varchar(255) NOT NULL,
  `md_date` date NOT NULL,
  PRIMARY KEY (`md_id`),
  KEY `FK_movie_date_movie` (`md_movie`),
  CONSTRAINT `FK_movie_date_movie` FOREIGN KEY (`md_movie`) REFERENCES `movie` (`mov_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

# Data exporting was unselected.


# Dumping structure for table mediabrary.movie_detail
CREATE TABLE IF NOT EXISTS `movie_detail` (
  `md_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `md_movie` int(10) unsigned NOT NULL DEFAULT '0',
  `md_name` varchar(255) NOT NULL DEFAULT '0',
  `md_value` varchar(255) NOT NULL DEFAULT '0',
  PRIMARY KEY (`md_id`),
  KEY `FK_movie_detail_movie` (`md_movie`),
  CONSTRAINT `FK_movie_detail_movie` FOREIGN KEY (`md_movie`) REFERENCES `movie` (`mov_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

# Data exporting was unselected.


# Dumping structure for table mediabrary.movie_float
CREATE TABLE IF NOT EXISTS `movie_float` (
  `mf_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mf_movie` int(10) unsigned NOT NULL,
  `mf_name` varchar(255) NOT NULL,
  `mf_value` float NOT NULL,
  PRIMARY KEY (`mf_id`),
  KEY `FK_movie_float_movie` (`mf_movie`),
  CONSTRAINT `FK_movie_float_movie` FOREIGN KEY (`mf_movie`) REFERENCES `movie` (`mov_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

# Data exporting was unselected.


# Dumping structure for table mediabrary.rate
CREATE TABLE IF NOT EXISTS `rate` (
  `rate_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rate_for` int(10) unsigned NOT NULL,
  `rate_from` int(10) unsigned NOT NULL,
  PRIMARY KEY (`rate_id`),
  UNIQUE KEY `rate_for` (`rate_for`),
  CONSTRAINT `FK_rate_movie` FOREIGN KEY (`rate_for`) REFERENCES `movie` (`mov_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

# Data exporting was unselected.
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;

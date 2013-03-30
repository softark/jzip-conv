DROP TABLE IF EXISTS `zip_data_temp`;
CREATE TABLE IF NOT EXISTS `zip_data_temp` (
  `id` int(11) NOT NULL,
  `m_zips` int(1),
  `m_zip_count` int(11),
  `m_blocks` int(1),
  `m_block_count` int(11),
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

insert into `zip_data_temp`
(
	`id`,
	`m_zips`,
	`m_zip_count`,
	`m_blocks`,
	`m_block_count`
)
select
	`t1`.`id`,
	`t1`.`m_zips`,
	(select
		count(*)
	from
		`zip_data` `t2`
	where
		`t1`.`pref` = `t2`.`pref`
	and
		`t1`.`town` = `t2`.`town`
	and
		`t1`.`block` = `t2`.`block`
	and
		`t1`.`zip_code` <> `t2`.`zip_code`
	and
		`t1`.`biz` = 0
	and
		`t2`.`biz` = 0
	)
	as `m_zip_count`,
	`t1`.`m_blocks`,
	(select
		count(*)
	from
		`zip_data` `t3`
	where
		`t1`.`zip_code` = `t3`.`zip_code`
	and
		(
			`t1`.`pref` <> `t3`.`pref`
			or
			`t1`.`town` <> `t3`.`town`
			or
			`t1`.`block` <> `t3`.`block`
		)
	and
		`t1`.`biz` = 0
	and
		`t3`.`biz` = 0
	)
	as `m_block_count`
from
	`zip_data` `t1`
;

update `zip_data` `dst`, `zip_data_temp` `temp`
set
	`dst`.`m_zips` = if (`temp`.`m_zip_count` > 0, 1, 0),
	`dst`.`m_blocks` = if (`temp`.`m_block_count` > 0, 1, 0)
where `dst`.`id` = `temp`.`id`
;

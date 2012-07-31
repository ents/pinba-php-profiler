-- Нужно поставить на крон. Интервал по желанию актуальности данных в выдаче

truncate table pinba_cache.request;
truncate table pinba_cache.tag;
truncate table pinba_cache.timer;
truncate table pinba_cache.timertag;


insert ignore into pinba_cache.request select * from pinba.request;
insert ignore into pinba_cache.tag select * from pinba.tag;
insert ignore into pinba_cache.timer select * from pinba.timer;
insert ignore into pinba_cache.timertag select * from pinba.timertag;


truncate table pinba_cache.timer_tag_tree;

insert ignore into pinba_cache.timer_tag_tree
SELECT * FROM (
	SELECT null, timer_id, request_id, hit_count, timer.value, GROUP_CONCAT(timertag.value) as tags
	, (select timertag.value from pinba_cache.timertag where timertag.timer_id=timer.id and tag_id = (select id from pinba_cache.tag where name='treeId')) as tree_id
	, (select timertag.value from pinba_cache.timertag where timertag.timer_id=timer.id and tag_id = (select id from pinba_cache.tag where name='treeParentId')) as tree_parent_id
	FROM pinba_cache.timertag force index (timer_id)
	LEFT JOIN pinba_cache.timer ON timertag.timer_id=timer.id
	where not tag_id in ((select id from pinba_cache.tag where name='treeId'), (select id from pinba_cache.tag where name='treeParentId'))
	group by timertag.timer_id
	order by timer_id
) as tmp
GROUP BY tree_id;

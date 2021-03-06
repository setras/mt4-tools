
use myfx;

set autocommit           = 0;
set collation_connection = 'latin1_german1_ci';


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- Signals
insert into t_signal (created, provider, provider_id, name, alias, currency) values
   (now(), 'myfxbook', '1614352', 'GoodBoyFX'    , 'goodboy'    , 'USD'),
   (now(), 'myfxbook', '1716266', 'GoodBoyFX MAM', 'goodboy-mam', 'USD');


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
commit;

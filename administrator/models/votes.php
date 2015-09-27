<?php

/**
 * @version     3.0.0
 * @package     com_imc
 * @copyright   Copyright (C) 2014. All rights reserved.
 * @license     GNU AFFERO GENERAL PUBLIC LICENSE Version 3; see LICENSE
 * @author      Ioannis Tsampoulatidis <tsampoulatidis@gmail.com> - https://github.com/itsam
 */
defined('_JEXEC') or die;

jimport('joomla.application.component.modellist');

/**
 * Methods supporting a list of Imc records.
 */
class ImcModelVotes extends JModelList {

    /**
     * Constructor.
     *
     * @param    array    An optional associative array of configuration settings.
     * @see        JController
     * @since    1.6
     */
    public function __construct($config = array()) {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'id', 'a.id',
                'issueid', 'a.issueid',
                'created', 'a.created',
                'updated', 'a.updated',
                'ordering', 'a.ordering',
                'state', 'a.state',
                'created_by', 'a.created_by',

            );
        }

        parent::__construct($config);
    }

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     */
    protected function populateState($ordering = null, $direction = null) {
        // Initialise variables.
        $app = JFactory::getApplication('administrator');

        // Load the filter state.
        $search = $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $search);

        $published = $app->getUserStateFromRequest($this->context . '.filter.state', 'filter_published', '', 'string');
        $this->setState('filter.state', $published);

        
		//Filtering issueid
		$this->setState('filter.issueid', $app->getUserStateFromRequest($this->context.'.filter.issueid', 'filter_issueid', '', 'string'));


        // Load the parameters.
        $params = JComponentHelper::getParams('com_imc');
        $this->setState('params', $params);

        // List state information.
        parent::populateState('a.id', 'asc');
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * This is necessary because the model is used by the component and
     * different modules that might need different sets of data or different
     * ordering requirements.
     *
     * @param	string		$id	A prefix for the store id.
     * @return	string		A store id.
     * @since	1.6
     */
    protected function getStoreId($id = '') {
        // Compile the store id.
        $id.= ':' . $this->getState('filter.search');
        $id.= ':' . $this->getState('filter.state');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return	JDatabaseQuery
     * @since	1.6
     */
    protected function getListQuery() {
        // Create a new query object.
        $db = $this->getDbo();
        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select(
                $this->getState(
                        'list.select', 'DISTINCT a.*'
                )
        );
        $query->from('`#__imc_votes` AS a');

        
		// Join over the users for the checked out user
		$query->select("uc.name AS editor");
		$query->join("LEFT", "#__users AS uc ON uc.id=a.checked_out");
		// Join over the foreign key 'issueid'
		$query->select('#__imc_issues_1382359.title AS issues_title_1382359');
		$query->join('LEFT', '#__imc_issues AS #__imc_issues_1382359 ON #__imc_issues_1382359.id = a.issueid');
		// Join over the user field 'created_by'
		$query->select('created_by.name AS created_by');
		$query->join('LEFT', '#__users AS created_by ON created_by.id = a.created_by');

        

		// Filter by published state
		$published = $this->getState('filter.state');
		if (is_numeric($published)) {
			$query->where('a.state = ' . (int) $published);
		} else if ($published === '') {
			$query->where('(a.state IN (0, 1))');
		}

        // Filter by search in title
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where('a.id = ' . (int) substr($search, 3));
            } else {
                $search = $db->Quote('%' . $db->escape($search, true) . '%');
                
            }
        }

		//Filtering issueid
		$filter_issueid = $this->state->get("filter.issueid");
		if ($filter_issueid) {
			$query->where("a.issueid = '".$db->escape($filter_issueid)."'");
		}

        // Filter by timestamp/prior to (Currently used only by API requests)
        $ts = $this->state->get('filter.imcapi.ts');
        if(!is_null($ts))
        {
            $query->where('UNIX_TIMESTAMP(a.updated) >=' . $ts);
        }

        // Filter by userid (Currently used only by API requests)
        $userid = $this->state->get('filter.imcapi.userid');
        if(!is_null($userid))
        {
            $query->where('a.created_by = ' . $userid);
        }

        // Add the list ordering clause.
        $orderCol = $this->state->get('list.ordering');
        $orderDirn = $this->state->get('list.direction');
        if ($orderCol && $orderDirn) {
            $query->order($db->escape($orderCol . ' ' . $orderDirn));
        }

        return $query;
    }

    public function getItems() {
        $items = parent::getItems();
        
/*		foreach ($items as $oneItem) {

			if (isset($oneItem->issueid)) {
				$values = explode(',', $oneItem->issueid);

				$textValue = array();
				foreach ($values as $value){
					$db = JFactory::getDbo();
					$query = $db->getQuery(true);
					$query
							->select('title')
							->from('`#__imc_issues`')
							->where('id = ' . $db->quote($db->escape($value)));
					$db->setQuery($query);
					$results = $db->loadObject();
					if ($results) {
						$textValue[] = $results->title;
					}
				}

			$oneItem->issueid = !empty($textValue) ? implode(', ', $textValue) : $oneItem->issueid;

			}
		}*/
        return $items;
    }

    public function hasVoted($issueid, $userid) {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('COUNT(*)');
        $query->from('`#__imc_votes` AS a');
        $query->where('a.issueid    = ' . $issueid);
        $query->where('a.created_by = ' . $userid);
        $db->setQuery($query);
        $results = $db->loadResult();
        return (boolean) $results;
    }

    public function remove($issueid, $userid)
    {
        // check if already voted
        if(!$this->hasVoted($issueid, $userid))
        {
            return array('code'=>0, 'msg'=>JText::_('COM_IMC_VOTES_CANNOT_UNVOTE'));
        }

        require_once JPATH_COMPONENT_ADMINISTRATOR . '/models/issues.php';
        $issuesModel = JModelLegacy::getInstance( 'Issues', 'ImcModel', array('ignore_request' => true) );

        $db = JFactory::getDbo();
        $query = $db->getQuery(true);

        $conditions = array(
            $db->quoteName('issueid') . ' = ' . $issueid,
            $db->quoteName('created_by') . ' = ' . $userid
        );

        $query->delete($db->quoteName('#__imc_votes'));
        $query->where($conditions);
        $db->setQuery($query);
        $result = $db->execute();

        if($result){
            //update issue votes as well
            $result = $issuesModel->updateVotes($issueid, false);
            if($result){
                //also return current number of votes
                $votes = $issuesModel->getVotes($issueid);
                return array('code'=>1, 'msg'=>JText::_('COM_IMC_VOTES_REMOVED'), 'votes'=>$votes);
            }
            else {
                return array('code'=>-1, 'msg'=>'failed to update issue');
            }
        } else {
            return array('code'=>-1, 'msg'=>'failed to insert into votes table');
        }
    }

    public function add($issueid, $userid, $modality = 0) {

        // check if already voted
        if($this->hasVoted($issueid, $userid)){
            return array('code'=>0, 'msg'=>JText::_('COM_IMC_VOTES_ALREADY_VOTED'));
        }

        //JModelLegacy::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/models');
        //require_once otherwise conflicts with site's issues model when called by API
        require_once JPATH_COMPONENT_ADMINISTRATOR . '/models/issues.php';
        $issuesModel = JModelLegacy::getInstance( 'Issues', 'ImcModel', array('ignore_request' => true) );

        // check if it's own issue
        if( $issuesModel->isOwnIssue($issueid, $userid) ){
            return array('code'=>0, 'msg'=>JText::_('COM_IMC_VOTES_OWN_ISSUE'));    
        }

        // check if issue is published
        if( !$issuesModel->isPublished($issueid))
        {
            return array('code'=>0, 'msg'=>'Issue is not published or does not exist');
        }

        // Create and populate an object.
        $vote = new stdClass();
        $vote->issueid = $issueid;
        $vote->created_by = $userid;
        $vote->state = 1;
        $vote->modality = $modality;
        $vote->created = date('Y-m-d H:i:s');
        $vote->updated = date('Y-m-d H:i:s');
         
        // Insert the object into the votes table.
        $db = JFactory::getDbo();
        $result = $db->insertObject('#__imc_votes', $vote); 
        if($result){
            //update issue votes as well
            $result = $issuesModel->updateVotes($issueid);
            if($result){
                //also return current number of votes 
                $votes = $issuesModel->getVotes($issueid);
                return array('code'=>1, 'msg'=>JText::_('COM_IMC_VOTES_ADDED'), 'votes'=>$votes);
            }
            else {
                return array('code'=>-1, 'msg'=>'failed to update issue');
            }
        } else {
            return array('code'=>-1, 'msg'=>'failed to insert into votes table');
        }
    }
}

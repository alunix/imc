<?php

/**
 * @version     3.0.0
 * @package     com_imc
 * @copyright   Copyright (C) 2014. All rights reserved.
 * @license     GNU AFFERO GENERAL PUBLIC LICENSE Version 3; see LICENSE
 * @author      Ioannis Tsampoulatidis <tsampoulatidis@gmail.com> - https://github.com/itsam
 */
// No direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.view');

/**
 * View class for a list of Imc.
 */
class ImcViewIssues extends JViewLegacy {

    protected $items;
    protected $pagination;
    protected $state;

    /**
     * Display the view
     */
    public function display($tpl = null) {
        $this->state = $this->get('State');
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');

        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new Exception(implode("\n", $errors));
        }

        ImcHelper::addSubmenu('issues');

        $this->addToolbar();

        $this->sidebar = JHtmlSidebar::render();
        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @since	1.6
     */
    protected function addToolbar() {
        require_once JPATH_COMPONENT . '/helpers/imc.php';

        $state = $this->get('State');
        $canDo = ImcHelper::getActions($state->get('filter.category_id'));

        JToolBarHelper::title(JText::_('COM_IMC_TITLE_ISSUES'), 'drawer');

        //Check if the form exists before showing the add/edit buttons
        $formPath = JPATH_COMPONENT_ADMINISTRATOR . '/views/issue';
        if (file_exists($formPath)) {

            if ($canDo->get('core.create')) {
                JToolBarHelper::addNew('issue.add', 'COM_IMC_JTOOLBAR_NEW_ISSUE');
            }

            if ($canDo->get('core.edit') && isset($this->items[0])) {
                JToolBarHelper::editList('issue.edit', 'JTOOLBAR_EDIT');
            }
        }

        if ($canDo->get('core.edit.state')) {

            if (isset($this->items[0]->state)) {
                JToolBarHelper::divider();
                JToolBarHelper::custom('issues.publish', 'publish.png', 'publish_f2.png', 'JTOOLBAR_PUBLISH', true);
                JToolBarHelper::custom('issues.unpublish', 'unpublish.png', 'unpublish_f2.png', 'JTOOLBAR_UNPUBLISH', true);
            } else if (isset($this->items[0])) {
                //If this component does not use state then show a direct delete button as we can not trash
                JToolBarHelper::deleteList('', 'issues.delete', 'JTOOLBAR_DELETE');
            }

            if (isset($this->items[0]->state)) {
                JToolBarHelper::divider();
                JToolBarHelper::archiveList('issues.archive', 'JTOOLBAR_ARCHIVE');
            }
            if (isset($this->items[0]->checked_out)) {
                JToolBarHelper::custom('issues.checkin', 'checkin.png', 'checkin_f2.png', 'JTOOLBAR_CHECKIN', true);
            }
        }

        // add batch button
        $bar = JToolBar::getInstance('toolbar');
        JHtml::_('bootstrap.modal', 'collapseModal');
        $title = JText::_('JTOOLBAR_BATCH');
        // Instantiate a new JLayoutFile instance and render the batch button
        $layout = new JLayoutFile('joomla.toolbar.batch');
        $dhtml = $layout->render(array('title' => $title));
        $bar->appendButton('Custom', $dhtml, 'batch');

        //Show trash and delete for components that uses the state field
        if (isset($this->items[0]->state)) {
            if ($state->get('filter.state') == -2 && $canDo->get('core.delete')) {
                JToolBarHelper::deleteList('', 'issues.delete', 'JTOOLBAR_EMPTY_TRASH');
                JToolBarHelper::divider();
            } else if ($canDo->get('core.edit.state')) {
                JToolBarHelper::trash('issues.trash', 'JTOOLBAR_TRASH');
                JToolBarHelper::divider();
            }
        }

        if ($canDo->get('core.admin')) {
            JToolBarHelper::preferences('com_imc');
        }

        //Set sidebar action - New in 3.0
        JHtmlSidebar::setAction('index.php?option=com_imc&view=issues');

        $this->extra_sidebar = '';
        
		JHtmlSidebar::addFilter(
			JText::_("JOPTION_SELECT_CATEGORY"),
			'filter_catid',
			JHtml::_('select.options', JHtml::_('category.options', 'com_imc'), "value", "text", $this->state->get('filter.catid'))
		);

        //Get custom field
        JFormHelper::addFieldPath(JPATH_ROOT . '/components/com_imc/models/fields');
        $steps = JFormHelper::loadFieldType('Step', false);
        $options = $steps->getOptions();

        JHtmlSidebar::addFilter(
            JText::_("COM_IMC_ISSUES_STEPID_FILTER"),
            'filter_stepid',
            JHtml::_('select.options', $options, "value", "text", $this->state->get('filter.stepid'), true)
        );

        JHtmlSidebar::addFilter(
            JText::_('JOPTION_SELECT_PUBLISHED'),
            'filter_published',
            JHtml::_('select.options', JHtml::_('jgrid.publishedOptions'), "value", "text", $this->state->get('filter.state'), true)
        );


    }

    protected function getSortFields()
    {
        return array(
        'a.id' => JText::_('JGRID_HEADING_ID'),
        'a.title' => JText::_('COM_IMC_ISSUES_TITLE'),
        'a.stepid' => JText::_('COM_IMC_ISSUES_STEPID'),
        'a.catid' => JText::_('COM_IMC_ISSUES_CATID'),
        'a.ordering' => JText::_('JGRID_HEADING_ORDERING'),
        'a.state' => JText::_('JSTATUS'),
        'a.access' => JText::_('JGRID_HEADING_ACCESS'),
        'a.updated' => JText::_('COM_IMC_ISSUES_UPDATED'),
        'a.created' => JText::_('COM_IMC_ISSUES_CREATED'),
        'a.created_by' => JText::_('COM_IMC_ISSUES_CREATED_BY'),
        'a.language' => JText::_('JGRID_HEADING_LANGUAGE'),
        );
    }
}

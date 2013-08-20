<?php

/**
 * Subsite support for Comment Admin.
 * 
 * Note that this will not work with a vanilla 2.4 install, you'll need the 
 * 	following at the top of CommentAdmin::EditForm():
 * 
 * 		//Allow this function to be overridden by extensions:
 * 		if ($ret = $this->extend("ExtendedEditForm"))
 * 			//note that it can only be extended once:
 * 			return array_pop($ret);
 *  
 * @author antisol
 *
 */

class CommentAdminSubsites extends Extension {
	
	/**
	 * An Extended version of CommentAdmin::EditForm which handles subsites
	 * @return Form
	 */
	function ExtendedEditForm() {
		$section = $this->owner->Section();

		if($section == 'approved') {
			$filter = "\"IsSpam\" = 0 AND \"NeedsModeration\" = 0";
			$title = "<h2>". _t('CommentAdmin.APPROVEDCOMMENTS', 'Approved Comments')."</h2>";
		} else if($section == 'unmoderated') {
			$filter = '"NeedsModeration" = 1';
			$title = "<h2>"._t('CommentAdmin.COMMENTSAWAITINGMODERATION', 'Comments Awaiting Moderation')."</h2>";
		} else {
			$filter = '"IsSpam" = 1';
			$title = "<h2>"._t('CommentAdmin.SPAM', 'Spam')."</h2>";
		}

		$filter .= ' AND "PageComment"."ParentID" > 0';
		
		//non-admin users see comments filtered by subsite:
		if (!Permission::check("ADMIN"))
			$filter .= ' AND SubsiteID = ' . Subsite::currentSubsiteID();
		
		$tableFields = array(
			"Name" => _t('CommentAdmin.AUTHOR', 'Author'),
			"Comment" => _t('CommentAdmin.COMMENT', 'Comment'),
			"Parent.Title" => _t('CommentAdmin.PAGE', 'Page'),
			"CommenterURL" => _t('CommentAdmin.COMMENTERURL', 'URL'),
			"Created" => _t('CommentAdmin.DATEPOSTED', 'Date Posted')
		);
		
		if (Permission::check("ADMIN"))
			$tableFields["Parent.Subsite.Client.Title"] = 'Client';

		$popupFields = new FieldSet(
			new TextField('Name', _t('CommentAdmin.NAME', 'Name')),
			new TextField('CommenterURL', _t('CommentAdmin.COMMENTERURL', 'URL')),
			new TextareaField('Comment', _t('CommentAdmin.COMMENT', 'Comment'))
		);

		$idField = new HiddenField('ID', '', $section);
		$table = new SubsiteCommentTableField(
			$this->owner, "Comments", "PageComment", $section, 
			$tableFields, $popupFields, array($filter), 
			'Created DESC',
			'LEFT JOIN "SiteTree" on "SiteTree"."ID" = "PageComment"."ParentID"
			 LEFT JOIN "Subsite" on "Subsite"."ID" = "SiteTree"."SubsiteID"
			'
		);
		
		$table->setParentClass(false);
		$table->setFieldCasting(array(
			'Created' => 'SS_Datetime->Full',
			'Comment' => array('HTMLText->LimitCharacters', 150)
		));
		
		$table->setPageSize(CommentAdmin::get_comments_per_page());
		$table->addSelectOptions(array('all'=>'All', 'none'=>'None'));
		$table->Markable = true;
		
		$fields = new FieldSet(
			new LiteralField("Title", $title),
			$idField,
			$table
		);

		$actions = new FieldSet();

		if($section == 'unmoderated') {
			$actions->push(new FormAction('acceptmarked', _t('CommentAdmin.ACCEPT', 'Accept')));
		}

		if($section == 'approved' || $section == 'unmoderated') {
			$actions->push(new FormAction('spammarked', _t('CommentAdmin.SPAMMARKED', 'Mark as spam')));
		}

		if($section == 'spam') {
			$actions->push(new FormAction('hammarked', _t('CommentAdmin.MARKASNOTSPAM', 'Mark as not spam')));
		}

		$actions->push(new FormAction('deletemarked', _t('CommentAdmin.DELETE', 'Delete')));

		if($section == 'spam') {
			$actions->push(new FormAction('deleteall', _t('CommentAdmin.DELETEALL', 'Delete All')));
		}

		$form = new Form($this->owner, "EditForm", $fields, $actions);

		return $form;
	}
	
	/**
	 * Return SQL JOIN clause to augment the ExtendedNum<x> Queries
	 * @return string
	 */
	function SubsiteJoin() {
		if (Permission::check("ADMIN")) return "";
		return 'LEFT JOIN 
					"SiteTree" 
				ON "SiteTree"."ID" = "PageComment"."ParentID"
				'; 
	}
	
	/**
	 * Return an SQL WHERE clause (with a trailing AND) to augment the 
	 * 	ExtendedNum<x> Queries
	 * @return string
	 */
	function SubsiteWhere($include_and = True) {
		if (Permission::check("ADMIN")) return "";
		//TODO: if Subsite::ClientSubsiteIDs() is a method, use that, 
		//	otherwise currentSubsiteID
		return '"SiteTree"."SubsiteID" IN (' . 
			convert::raw2sql(Subsite::currentSubsiteID()) . 
			") " . ($include_and ? " AND " : "");
	}
	
	/**
	 * Return the number of moderated comments
	 */
	function ExtendedNumModerated() {
		return DB::query("SELECT COUNT(*) FROM \"PageComment\" " . 
				$this->SubsiteJoin() . " WHERE " . $this->SubsiteWhere() . 
				"\"IsSpam\"=0 AND \"NeedsModeration\"=0")->value();
	}

	/**
	 * Return the number of unmoderated comments
	 */
	function ExtendedNumUnmoderated() {
		return DB::query("SELECT COUNT(*) FROM \"PageComment\"  " . 
				$this->SubsiteJoin() . " WHERE " . $this->SubsiteWhere() . 
				"\"IsSpam\"=0 AND \"NeedsModeration\"=1")->value();
	}

	/**
	 * Return the number of comments marked as spam
	 */
	function ExtendedNumSpam() {
		return DB::query("SELECT COUNT(*) FROM \"PageComment\"  " . 
			$this->SubsiteJoin() . " WHERE " . $this->SubsiteWhere() . 
			"\"IsSpam\"=1")->value();
	}
	
	
}

/**
 * Special kind of CommentTableField for managing comments in multiple 
 * 	subsites.
 */
class SubsiteCommentTableField extends CommentTableField {
	
	function __construct($controller, $name, $sourceClass, $mode, $fieldList, 
			$detailFormFields = null, $sourceFilter = "", 
			$sourceSort = "Created", $sourceJoin = "") {
		$this->mode = $mode;
		
		Session::set('CommentsSection', $mode);
		
		parent::__construct($controller, $name, $sourceClass, $mode, $fieldList, 
			$detailFormFields = null, $sourceFilter, 
			$sourceSort, $sourceJoin);
				
		$this->Markable = true;
		
		// Note: These keys have special behaviour associated through TableListField.js
		$this->selectOptions = array(
			'all' => _t('CommentTableField.SELECTALL', 'All'),
			'none' => _t('CommentTableField.SELECTNONE', 'None')
		);
		
		// search
		$search = isset($_REQUEST['CommentSearch']) ? Convert::raw2sql($_REQUEST['CommentSearch']) : null;
		if(!empty($_REQUEST['CommentSearch'])) {
			$this->sourceFilter[] = "( \"Name\" LIKE '%$search%' OR \"Comment\" LIKE '%$search%')";
		}
	}
	
	function Items() {
		$this->sourceItems = $this->sourceItems();
		
		if(!$this->sourceItems) {
			return null;
		}
		
		$pageStart = (isset($_REQUEST['ctf'][$this->Name()]['start']) && is_numeric($_REQUEST['ctf'][$this->Name()]['start'])) ? $_REQUEST['ctf'][$this->Name()]['start'] : 0;
		$this->sourceItems->setPageLimits($pageStart, $this->pageSize, $this->totalCount);
		
		$output = new DataObjectSet();
		foreach($this->sourceItems as $pageIndex=>$item) {
			$output->push(Object::create('SubsiteCommentTableField_Item',$item, $this, $pageStart+$pageIndex));
		}
		return $output;
	}
	
	function handleItem($request) {
		return new SubsiteCommentTableField_ItemRequest($this, $request->param('ID'));
	}
	
}

/**
 * Single row of a {@link SubsiteCommentTableField}
 */
class SubsiteCommentTableField_Item extends CommentTableField_Item {
	
}


class SubsiteCommentTableField_ItemRequest extends CommentTableField_ItemRequest {

}


?>
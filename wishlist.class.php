<?php
session_start();

class wishlist extends db{

	/*
	Method: getCurrentUserWishlist	
		Fetches and returns an array of items for the currently logged-in user.
		
	*/

	function getCurrentUserWishlist(){
		$query = "select * from {$this->options['table_prefix']}items where userid = '{$_SESSION['userid']}'";
		
		$result = $this->dbQuery($query);
		$list = $this->dbAssoc($result);
	
		return $list;
	}

	/*
	Method: getShoppingForList	
		Fetches and returns an array of items for the requested user id if current logged-in user is allowed to view.
		
		@args - array of arguments for this function
			@shopForId: The user id for the requested user's shopping list.
	*/
	function getShoppingForList($args){
	
		if($args['shopForId'] == $_SESSION['userid']){
			return array(
				'responseType'=>"error",
				'title'=>'Shopping for yourself?',
				'message'=>"It looks like you just tried to shop for yourself. You can't do that!"
				);
				
		}else{
	
			$query = "select
				     itemid,
				     description,
				     ranking,
				     category,
				     	(select min(sourceprice)
				          from {$this->options['table_prefix']}itemsources
				          where itemsources.itemid = items.itemid)
				     as price,
				     quantity,
					quantity -
						(select allocs.quantity from {$this->options['table_prefix']}allocs where allocs.itemid = items.itemid)
				     as available
				from
				     {$this->options['table_prefix']}items,
					{$this->options['table_prefix']}shoppers
				where
					{$this->options['table_prefix']}items.userid = '{$args['shopForId']}' and 
					{$this->options['table_prefix']}shoppers.shopper = '{$_SESSION['userid']}' and
					{$this->options['table_prefix']}shoppers.mayshopfor = '{$args['shopForId']}'";
				
			$result = $this->dbQuery($query);
			return $this->dbAssoc($result);
		}
	}
	
	/*
	Method: getCurrentCount	
		Gets the current quantity available for reserving and purchasing based on current user.
		
		@args - array of arguments for this function
			int @itemid: The id of the item which is being affected
			int @userid: The id of the user affecting 
			string @allocateAction: purchase, reserve, return, release
			int @adjustment: Quantity to adjust by, positive or negative	
	*/
	
	function getCurrentCount($args){
	
		switch($args['allocateAction']){
			case 'purchase':
				/* 
					This query returns the reserved and purchased values for a particular item for a particular user.
					provides you with the number remaining purchasable.
					
					In order to buy, we're only checking the allocs table - not the items table.
					You must reserve before you buy.
				*/
			
				$query = "select 
						(
							{$this->options['table_prefix']}allocs.quantity - 
							{$this->options['table_prefix']}allocs.bought
						) as availableToBuy	
					from 
						{$this->options['table_prefix']}allocs
					where
						{$this->options['table_prefix']}allocs.itemid = '{$args['itemid']}' and
						{$this->options['table_prefix']}allocs.userid = '{$args['userid']}' 
						"; 
			break;
			case 'reserve':
				/*
					This query gets a total sum of all reservations for an item and the items requested quantity 
					and returns the difference.
					
					Provides you with the number remaining reservable.
				*/
			
				$query = "select 
						(
							{$this->options['table_prefix']}items.quantity - 
							sum({$this->options['table_prefix']}allocs.quantity)
						) as availableToReserve,
						{$this->options['table_prefix']}items.itemid as item
					from 
						{$this->options['table_prefix']}items, 
						{$this->options['table_prefix']}allocs
					where 
						{$this->options['table_prefix']}allocs.itemid = '{$args['itemid']}' and 
						{$this->options['table_prefix']}items.itemid = '{$args['itemid']}' 
						";			
			break;
			case 'return':
			case 'release':
				$query = "select 
							{$this->options['table_prefix']}allocs.* 
						from 
							{$this->options['table_prefix']}allocs
						where
							{$this->options['table_prefix']}allocs.itemid = '{$args['itemid']}' and 
							{$this->options['table_prefix']}allocs.userid = '{$args['userid']}'
						";
			break;
		}
			$result = $this->dbQuery($query);
			return $this->dbAssoc($result);	
	}
		
	/*
	Method: adjustReservedItem	
		Reserves, Releases, Purchases, or Returns item according to arguments provided 
		
		@args - array of arguments for this function
			int @itemid: The id of the item which is being affected
			int @userid: The id of the user affecting 
			string @allocateAction: buy,return, reserve, release
			int @adjustment: Quantity to adjust by, positive or negative
			
	*/	
	function adjustReservedItem($args){
		
		$query = "";
		
		switch($args['allocateAction']){
			case 'reserve':
			
				$currentCount = $this->getCurrentCount($args);	

				//Check if we've reserved any of this before.
				if($currentCount['availableToReserve'] != null){

					//we check that we're not trying to reserve more than we can.
					if($currentCount['availableToReserve'] <= $args['adjustment']){
						//we set our query to update to the new value
						$query = "update 
									{$this->options['table_prefix']}allocs 
								set 
									{$this->options['table_prefix']}allocs.quantity = 
										{$this->options['table_prefix']}allocs.quantity + {$args['adjustment']}
								where 
									{$this->options['table_prefix']}allocs.itemid = {$args['itemid']} and
									{$this->options['table_prefix']}allocs.userid = {$args['userid']}
									";
						
					}else{
						//we return an error saying the requested reservation is too high.
						$error = array("errorMessage"=>"Reservation quantity was greater than requested quantity.");
						return $error;
					}
					
				}else{
					//we isnert the row into the alloc table.
					$query = "insert into {$this->options['table_prefix']}allocs(
								itemid,userid,bought,quantity
								) 
								values(
									'{$args['itemid']}',
									'{$args['userid']}',
									0,
									'{$args['adjustment']}'
								)";
				}
				
			break;
			case 'release':
				$currentCount = $this->getCurrentCount($args);
								
				//check to see if we've reserved anything before now:
				if(($currentCount['quantity'] - $currentCount['bought']) >= $args['adjustment'] && $currentCount['bought'] > 0){
				
					$query = "update 
								{$this->options['table_prefix']}allocs 
							set 
								{$this->options['table_prefix']}allocs.quantity = 
									{$this->options['table_prefix']}allocs.quantity - {$args['adjustment']}
							where 
								{$this->options['table_prefix']}allocs.itemid = {$args['itemid']} and
								{$this->options['table_prefix']}allocs.userid = {$args['userid']}
								";
					
				//We want to deleted the row if we don't need it anymore.
				}else if($currentCount['quantity'] == $args['adjustment'] &&  $currentCount['bought'] == 0){
					$query = "delete from 
								{$this->options['table_prefix']}allocs					
							where 
								{$this->options['table_prefix']}allocs.itemid = {$args['itemid']} and
								{$this->options['table_prefix']}allocs.userid = {$args['userid']}
							";
				}else{
					$error = array("errorMessage"=>"Unpurchased Reserved quantity is less than requested adjustment");
					return $error;
				}
				
				
			break;
			case 'purchase':
				$currentCount = $this->getCurrentCount($args);
				
				//Check to make sure that the reservation row exists.
				if(count($currentCount) > 0){
				
					//Check to make sure what we're not buying more than we've reserved.
					if($currentCount['availableToBuy'] >= $args['adjustment']){
						$query = "update 
									{$this->options['table_prefix']}allocs 
								set 
									{$this->options['table_prefix']}allocs.bought = 
										{$this->options['table_prefix']}allocs.bought + {$args['adjustment']}
								where 
									{$this->options['table_prefix']}allocs.itemid = {$args['itemid']} and
									{$this->options['table_prefix']}allocs.userid = {$args['userid']}
									";
					}else{
						//we return an error saying the requested purchase count is too high.
						$error = array("errorMessage"=>"Purchase quantity was greater than reserved quantity.");
						return $error;
					}
					
				}else{
					$error = array("errorMessage"=>"You have not reserved this item.");
					return $error;					
				}
				
				
			break;
			case 'return':
				$currentCount = $this->getCurrentCount($args);
				
				if($currentCount['bought'] >= $args['adjustment']){
					$query = "update 
									{$this->options['table_prefix']}allocs 
								set 
									{$this->options['table_prefix']}allocs.bought = 
										{$this->options['table_prefix']}allocs.bought - {$args['adjustment']}
								where 
									{$this->options['table_prefix']}allocs.itemid = {$args['itemid']} and
									{$this->options['table_prefix']}allocs.userid = {$args['userid']}
							";
				}else{
					$error = array("errorMessage"=>"Trying to return more than you've bought!");
					return $error;					
				}
				
			break;
		}
		print $query;	

		//	$result = $this->dbQuery($query);
		//	return $this->dbAssoc($result);				
	}
	


	/*
		Method: manageItem
		Adds, Edits or Deletes an item from the database.

		str @itemAction: The action name for managing an item: add,edit,or delete.
		
		int @userid: The id of the user who owns this item - note: does not have to be the logged in user
		str @description: The 'name' of the item 
		int @ranking: The rank according to user.
		int @category: integer based on categories table.
		str @comment: any comment the user wishes to share about the item.
		int @quantity: How many of this item the user would like.		
		
	*/
	
	function manageItem($args){
				
		switch($args['itemAction']){
			case 'add':
				$query = "insert into items(userid, description,ranking,category,comment,quantity)
						values(
							'{$this->dbEscape($args['userid'])}',
							'{$this->dbEscape($args['description'])}',
							'{$this->dbEscape($args['ranking'])}',
							'{$this->dbEscape($args['category'])}',
							'{$this->dbEscape($args['comment'])}',
							'{$this->dbEscape($args['quantity'])}'
						)
				";			
			break;
			case 'edit':
				$query = "update items set 
						description = '{$this->dbEscape($args['description'])}',
						ranking = '{$this->dbEscape($args['ranking'])}',
						category = '{$this->dbEscape($args['category'])}',
						comment = '{$this->dbEscape($args['comment'])}',
						quantity = '{$this->dbEscape($args['quantity'])}'
					where
						itemid = {$this->dbEscape($args['itemid'])}
				";
			
			break;
			case 'delete':
				$query = "delete from items where itemid = {$this->dbEscape($args['itemid'])}";
				
			break;			
		}

		$result = $this->dbQuery($query);
		
		if($args['itemAction'] == 'add'){
			return $this->dbLastInsertId();
		}else{
			return $result;
		}
	}
	
	/*
		method manageSource:
		This function adds or removes sources for a particular item.
		int @itemid: The itemid that this source is for
		string @source: The name of the source: A store or website
		string @sourceurl: The URL for the source
		string @sourceprice: The price for the source
		int @addedByUserId: The userId who provided the source. - other users can offer sources for products.
	*/
	function manageItemSource($args){

		switch($args['itemSourceAction']){
			case 'add':
				$query = "insert into itemsources(itemid,source,sourceurl,sourceprice,sourcecomments,addedByUserId)
					values(
						'{$this->dbEscape($args['itemid'])}',
						'{$this->dbEscape($args['source'])}',
						'{$this->dbEscape($args['sourceurl'])}',																				
						'{$this->dbEscape($args['sourceprice'])}',
						'{$this->dbEscape($args['sourcecomments'])}',						
						'{$_SESSION['userid']}'
					)";		
			break;
			case 'edit':
				$query = "update itemsources set
						source = '{$this->dbEscape($args['source'])}',
						sourceurl = '{$this->dbEscape($args['sourceurl'])}',
						sourceprice = '{$this->dbEscape($args['sourceprice'])}',
						sourcecomments = '{$this->dbEscape($args['sourcecomments'])}'
					where
						sourceid = '{$this->dbEscape($args['sourceid'])}' and
						addedByUserId = '{$_SESSION['userid']}'
				";
			break;
			case 'delete':
				$query = "delete from itemsources where sourceid = {$args['sourceid']}";
			break;
		}
		
		error_log($query);
		
		$result = $this->dbQuery($query);
		return $result;
	}
	
	/*
		Method: manageItemImage
		int @itemid: The item this image will be associated with 
		string @filename: The name of the file that was uploaded.
		str @itemImageAction: The desired action: add or delete.
	*/	
	function manageItemImage($args){
			
		switch($args['itemImageAction']){
			case 'add':
			
				//Random name to prevent overwriting files.
				$randName = substr(md5(uniqid(rand(), true)),0,10).$_FILES['uploadfile']['name'];
				
				$moveFile = move_uploaded_file($_FILES['uploadfile']['tmp_name'], $this->options['filepath'].$randName); //
				
				$query = "insert into itemimages(itemid,filename) values(
					'{$this->dbEscape($args['itemid'])}',
					'{$this->dbEscape($randName)}'
				)";
			break;
			case 'delete':
				
				$filenameQuery = "select filename from itemimages where imageid = '{$this->dbEscape($args['imageid'])}'";
				$filename = $this->dbValue($this->dbQuery($filenameQuery));
				
				$deleteResult = unlink($this->options['filepath'].$filename);
			
				if($deleteResult){
					$query = "delete from itemimages where imageid = '{$this->dbEscape($args['imageid'])}'";
				}else{
					return $error = array('errorMessage'=>"There was a problem deleting the image file: ".$deleteResult);
				}
				
			break;
		}

		
		$result = $this->dbQuery($query);
		return $result;		
	}
	
	
	/*
		Method: addItem
		A non-abstract version for adding items to the list in general - adds and item, then it's images and sources.
	*/
	function addItem($args){
		
		$itemId = manageItem($args);
		
				
	}
	
	
	
	
	/*
		Method: getItemDetails
		Fetches the item details for a particular item and returns them in a associative array.
		returns an associative array containing item info, images, sources, and alloc data.
		
		int @itemid: The item to request details about.
	*/
	function getItemDetails($args){
		
		
		$query = "
			(select
			'item' as rowType,
			items.itemid as commonId,
			items.description as itemDescription,
			items.ranking as itemRanking,
			items.category as itemCategory,
			items.comment as itemComment,
			items.quantity as itemQuantity,
			items.addedByUserId as itemAddedByUserId,

			'' as itemSourceId,
			'' as itemSource,
			'' as itemSourceUrl,
			'' as itemSourcePrice,
			'' as itemSourceComments,

			'' as itemImageId,
			'' as itemImageFilename,

			'' as itemAllocUserId,
			'' as itemAllocUserName,			
			'' as itemAllocBought,
			'' as itemAllocQuantity

			from items where itemid = '{$args['itemid']}'
			)
			UNION

			(select
			'source' as rowType,
			itemsources.itemid as commonId,
			'' as itemDescription,
			'' as itemRanking,
			'' as itemCategory,
			'' as itemComment,
			'' as itemQuantity,
			'' as itemAddedByUserId,

			itemsources.sourceid as itemSourceId,
			itemsources.source as itemSource,
			itemsources.sourceurl as itemSourceUrl,
			itemsources.sourceprice as itemSourcePrice,
			itemsources.sourcecomments as itemSourceComments,

			'' as itemImageId,
			'' as itemImageFilename,

			'' as itemAllocUserId,
			'' as itemAllocUserName,		
			'' as itemAllocBought,
			'' as itemAllocQuantity

			from itemsources where itemsources.itemid = '{$args['itemid']}'
			)

			UNION

			(select
			'image' as rowType,
			itemimages.itemid as commonId,
			'' as itemDescription,
			'' as itemRanking,
			'' as itemCategory,
			'' as itemComment,
			'' as itemQuantity,
			'' as itemAddedByUserId,

			'' as itemSourceId,
			'' as itemSource,
			'' as itemSourceUrl,
			'' as itemSourcePrice,
			'' as itemSourceComments,

			itemimages.imageid as itemImageId,
			itemimages.filename as itemImageFilename,

			'' as itemAllocUserId,
			'' as itemAllocUserName,
			'' as itemAllocBought,
			'' as itemAllocQuantity

			from itemimages where itemimages.itemid = '{$args['itemid']}'
			)

			UNION

			(select
			'alloc' as rowType,
			allocs.itemid as commonId,
			'' as itemDescription,
			'' as itemRanking,
			'' as itemCategory,
			'' as itemComment,
			'' as itemQuantity,
			'' as itemAddedByUserId,

			'' as itemSourceId,
			'' as itemSource,
			'' as itemSourceUrl,
			'' as itemSourcePrice,
			'' as itemSourceComments,

			'' as itemImageId,
			'' as itemImageFilename,

			allocs.userid as itemAllocUserId,
			users.fullname as itemAllocUserName,
			allocs.bought as itemAllocBought,
			allocs.quantity as itemAllocQuantity

			from allocs,users 
			where 
				allocs.itemid = '{$args['itemid']}' and
				users.userid = allocs.userid
			)";
		
			$result = $this->dbQuery($query);
			$itemDetailArray = $this->dbAssoc($result,true);
			
			$itemDetails = array();
			
			foreach($itemDetailArray as $line){

				//eliminate empty values that are a carry-over from the Query.				
				foreach($line as $key => $value){
					if($value == null){
						unset($line[$key]);
					}
				}
				
				switch($line['rowType']){
					case 'item':
						$itemDetails = $line;
					break;
					case 'source':
						$itemDetails['sources'][] = $line;
					break;
					
					case 'image':
						$itemDetails['images'][] = $line;					
					break;
						
					case 'alloc':
						$itemDetails['allocs'][] = $line;					
					break;									
				}
			}
			
		return $itemDetails;
	}


	/*
		Method: getCategories
		Fetches a list of categories with IDs and names
	*/
	function getCategories(){
		
		$query = "select * from categories";
		$categoryResult = $this->dbQuery($query);
		
		return $this->dbAssoc($categoryResult);
	}

}

	


?>
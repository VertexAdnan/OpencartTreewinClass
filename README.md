# OpencartTreewinClass
Basic usage of treewin &amp; opencart

# OpencartTreewinClass
Treewin Class:
system\library\cart\treewin.php

AutoLoading #admin\controller\startup\startup.php:
		// Treewin
		$this->registry->set('treewin', new Cart\Treewin($this->registry));

AutoLoading #catalog\controller\startup\startup.php:
		// Treewin
		$this->registry->set('treewin', new Cart\Treewin($this->registry));

AutoPushing #catalog\controller\checkout\success.php
    $this->treewin->saveContract($this->session->data['order_id']);

ManuelPushing #admin\controller\sale\order.php
	public function save(){
		$this->treewin->saveContract(isset($_GET['order_id']) ? $_GET['order_id'] : 0);
		header('Location: ' . $_SERVER['HTTP_REFERER'] . '&success=true');
	}

ManuelPushing #admin\view\template\sale\order_list.twig
<div class="btn-group">
    <a href="{{order.save}}" data-toggle="tooltip" title="Kaydet" class="btn btn-primary"><i class="fa fa-floppy-o"></i></a>
</div>


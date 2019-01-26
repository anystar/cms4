<?php
use Coyl\Git\Git;
use Coyl\Git\GitRepo;

class version_control extends prefab {

	private $repo;
	private $gitignore_sha1 = "368f1375c9fc398943dc072b344e77ebe6410cfb";
	private $branch;

	function __construct($settings) {

		if (!admin::$signed)
			return;

		if ($settings["version-control"] != "true")
			return;
		
		$f3 = base::instance();

		check (0, !array_key_exists("git", $f3->CONFIG), "No `git` section in config.ini");
		check (0, !array_key_exists("path", $f3->CONFIG["git"]), "No `git_path` set in config.ini");

		Git::setBin ($f3->CONFIG["git"]["path"]);

		$this->repo = new GitRepo(getcwd(), true);

		if (array_key_exists("save", $f3->GET))
			$this->save();

		// If we are in a detached HEAD, lets get branch from cache
		if ($this->isDetached())
		{
			// We have to be careful about this though..
			$this->branch = Cache::instance()->get("gitbranch");
		}
		else
			$this->branch = $branch = $this->repo->getActiveBranch();


		// Ensure user and email are setup
		$check = $this->repo->run("config --get user.name");
		if ($check == "")
			$this->repo->run("config user.name '".$f3->CONFIG["git"]["name"]."'");

		$check = $this->repo->run("config --get user.email");
		if ($check == "")
			$this->repo->run("config user.email '".$f3->CONFIG["git"]["email"]."'");

		$check = $this->repo->run("config --get core.safecrlf");
		if ($check == "")
			$this->repo->run("config core.safecrlf false");

		// Add our shortcuts
		$nextCmd = "!sh -c 'git log --reverse --pretty=%H ".$this->branch." | awk \"/$(git rev-parse HEAD)/{getline;print}\" | xargs git checkout'";

		$check = $this->repo->run("config --get-all alias.next");
		$check = preg_split("/\\r\\n|\\r|\\n/", $check);
		$check = array_filter($check);
		$check = array_values($check);
		
		if (count($check) > 1)
		{
			$this->repo->run("config --unset-all alias.next");
			$check = "";
		}
		else {
			$check = end($check);
		}

		if ($check != $nextCmd)
		{
			$this->repo->run("config --unset-all alias.next");
			$this->repo->run("config --add alias.next \"!sh -c 'git log --reverse --pretty=%%H ".$this->branch." | awk \\\"/$(git rev-parse HEAD)/{getline;print}\\\" | xargs git checkout'\"");
		}

		$check = $this->repo->run("config --get alias.prev");
		if ($check == "")
			$this->repo->run("config --add alias.prev \"checkout HEAD^1\"");

		$gitignore = ".cms/tmp".PHP_EOL.".cms/cache".PHP_EOL.".cms/stats.json".PHP_EOL."cms.php".PHP_EOL."error_log".PHP_EOL.".htaccess";

		// Ensure our .gitignore exists
		if (!file_exists(getcwd()."/.gitignore"))
			file_put_contents(getcwd()."/.gitignore", $gitignore);

		else if (sha1_file(getcwd()."/.gitignore") != $this->gitignore_sha1)
			file_put_contents(getcwd()."/.gitignore", $gitignore);

		if (isroute("/admin/versioncontrol/save"))
		{
			$this->save();
			echo $this->getState(true);
			die;
		}

		if (array_key_exists("undo", $f3->GET))
			$this->undo();
		else if (array_key_exists("redo", $f3->GET))
			$this->redo();
		else if (array_key_exists("save-changes", $f3->GET))
		{
			$this->save();
			$f3->reroute($f3->PATH);
		}

		$f3->route("POST /admin/versioncontrol/poll [ajax]", function ($f3) {
			echo $this->getState(true);
			exit;
		});

		if (isroute("/admin/versioncontrol/push")) {
			$this->push();
			echo $this->getState(true);
			die;
		}

		ToolBar::instance()->append(Template::instance()->render("/revision-control/toolbar.html", null, ["state"=>$this->getState(true), "BASE"=>$f3->BASE]));
	}

	function getState ($json = false) {

		$state["isDirty"] = $this->isDirty();
		$state["canUndo"] = $this->canUndo();
		$state["canRedo"] = $this->canRedo();
		$state["detachedDirty"] = $this->detachedAndDirty();
		$state["locked"] = $this->isLocked();
		$state["canPush"] = $this->canPush();
		$state["canPull"] = $this->canPull();
		$state["isRemoteBehind"] = $this->isRemoteBehind();

		if ($json)
			return json_encode($state);
		else
			return $state;
	}

	function hasUpstream () {

		$remote = preg_split("/\\r\\n|\\r|\\n/", rtrim($this->repo->run("remote"), "\n\r"));

		if ($remote == "")
			return false;
		else if ($remote == "origin")
			return true;
		else if (is_array($remote))
			if (in_array("origin", $remote))
				return true;

		return false;
	}

	function isDirty() {

		// Are we in a detached head?
		if (substr($this->repo->run("status"), 0, 16) == "HEAD detached at")
			return true;

		if ($this->repo->run("status -s") == "")
			return false;
		else
			return true;
	}

	function getHistory () {
		$history = $this->repo->run("log --oneline --format='%%H'");
		$history = preg_split("/\\r\\n|\\r|\\n/", $history);
		$history = array_filter($history);

		return $history;
	}

	function canUndo () {

		$history = $this->getHistory();

		if (count($history) > 1)
			return true;
		else
			return false;	
	}

	function canRedo () {


		// Are we on a detached head?
		if ($this->isDetached())
			return true;
		else
			return false;
	}

	function detachedAndDirty () {

		// Are we in a detached head?
		if ($this->isDetached())
			if ($this->repo->run("status -s") != "")
				return true;

		return false;
	}

	function isDetached () {
		if (substr($this->repo->run("status"), 0, 16) == "HEAD detached at")
			return true;
		else
			return false;
	}

	function isLocked () {

		if (file_exists(getcwd()."/.git/index.lock"))
			return true;
		else
			return false;
	}

	function save () 
	{

		// No point continuing if repo is locked
		if ($this->isLocked())
			return;

		// If we are in a detached head, we better merge back to master
		if (substr($this->repo->run("status"), 0, 16) == "HEAD detached at")
		{
			$this->repo->run("checkout -b temp");

			$this->repo->checkout($this->branch);
			$this->repo->run("merge temp --strategy-option=theirs");
			$this->repo->delete_branch("temp");

			return;
		}

		$this->repo->add(".");
		$this->repo->commit("time: ".date("h:i:s"));
	}

	function undo () {

		// No point continuing if repo is locked
		if ($this->isLocked())
			return;

		// Destory changes
		if ($this->detachedAndDirty())
		{
			if (!array_key_exists("discard-changes", base::instance()->GET))
			{
				check(3, true, "WARNING: Your about to destory changes you made while undo'ing work.<br><br> *Basically what you've done is hit the undo button, made a change, and then hit the undo button again. Saving now will bring these changes forward in history which may disorientate you. Discarding on the other hand will lose the changes but you'll proceed to undo.*");
				die;
			}
			else {
				$this->repo->run("checkout .");
			}
		}

		// If we are not on a detached head
		if (substr($this->repo->run("status"), 0, 16) != "HEAD detached at")
		{
			// We are moving into a detached HEAD state
			// Lets save the branch we are on
			Cache::instance()->set("gitbranch", $this->branch, 0);

			if ($this->repo->run("status -s") != "")
				$this->save();
		}

		$this->repo->run("prev");

		base::instance()->reroute(base::instance()->PATH);
	}

	function redo () {

		// No point continuing if repo is locked
		if ($this->isLocked())
			return;

		// Destory changes
		if ($this->detachedAndDirty())
		{
			if (!array_key_exists("discard-changes", base::instance()->GET))
			{
				check(3, true, "WARNING: Your about to destory changes you made while undo'ing work.<br><br> *Basically what you've done is hit the undo button, made a change, and then hit the redo button. Saving now will bring these changes forward in history which may disorientate you. Discarding on the other hand will lose the changes but you'll proceed to redo.*");
				die;
			}
			else {
				$this->repo->run("checkout .");
			}
		}

		$this->repo->run("next");

		// We must be at the start, lets just checkout branch
		$current_ref = $this->repo->run("rev-parse HEAD");
		$master_ref = $this->repo->run("rev-parse ".$this->branch);

		if ($current_ref == $master_ref)
		{
			$this->repo->checkout($this->branch);
		}

		base::instance()->reroute(base::instance()->PATH);
	}

	function canPush () {

		// Make sure we have an upstream
		if (!$this->hasUpstream())
			return false;

		// Prevent pushing on master branch
		if ($this->branch != "remote")
			return false;

		// Prevent pushing when changes need to be saved 
		if ($this->isDirty())
			return false;

		// Only push when we are ahead
		if (!$this->isAhead())
			return false;

		// Do not push when we are behind
		// if (!$this->isBehind())
		// 	return false;

		return true;

	}

	function canPull () {

		// Make sure we have an upstream
		if (!$this->hasUpstream())
			return false;

		// Prevent pulling on any other branch
		if ($this->branch != "remote")
			return false;

		// Prevent pulling when changes need to be saved 
		if ($this->isDirty())
			return false;

		// Prevent pulling when we are ahead
		if ($this->isAhead())
			return false;

		// Only pull when we are behind
		 if (!$this->isBehind())
		 	return false;

		return true;
	}

	function push () {

		if (!$this->canPush())
			return false;

		$this->repo->run("push origin remote");
	}

	function isBehind () {

		//$this->fetch();

		if ($this->repo->run("rev-list --count remote..origin/remote") > 0)
			return true;
		else
			return false;
	}

	function isAhead () {

		//$this->fetch();

		if ($this->repo->run("rev-list --count origin/remote...remote") > 0)
			return true;
		else
			return false;
	}

	function isRemoteBehind ()
	{
		//$this->fetch();

		if ($this->repo->run("rev-list --count origin/master...remote") > 0)
			return true;
		else
			return false;
	}

	function fetch () {
		
		if (!$this->hasFetched)
			$this->repo->run("fetch --dry-run");

		$this->hasFetched = true;

	}

}
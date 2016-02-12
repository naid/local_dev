<?php
namespace App\Http\Controllers;

use App\Category;
use App\Follow;
use App\Http\Controllers\Controller;
use App\Set;
use App\Studying;
use App\User;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Session;

class SetController extends Controller
{

    protected $categories;

    public function __construct()
    {
        parent::__construct();
        $this->categories = Category::lists('name', 'id');
        session()->keep('setId');
        session()->keep('maxQuestions');
        session()->keep('questionIndex');
        session()->keep('termCount');
        session()->keep('setName');
    }

    public function listCustom($id, $type)
    {
        $set = new Set;
        switch ($type) {
            case 'created':
                $sets = $set->getUserCreatedSet($this->user->id);
                $title = 'Sets You Created';
                break;
            case 'studying':
                $sets = $set->getUserStudyingSet($this->user->id);
                $title = 'Sets You Are Currently Studying';
                break;
            case 'recommended':
                $sets = $set->getRecommendedSets($this->user->id);
                $title = 'Recommended Sets';
                break;

            default:
                $sets = $set->getSetsForUser($this->user->id);
                $title = 'Available Sets';
                break;
        }

        return view('sets.index', [
            'sets' => $sets,
            'user' => $this->user,
            'title' => $title,
        ]);
    }

    public function index()
    {
        $sets = Set::all();
        $follow = new Follow;

        foreach ($sets as $key => $set) {
            $isFollower = $follow->isFollower($this->user->id, $set->user_id);

            $isFollowee = $follow->isFollowee($this->user->id, $set->user_id);
            switch ($set->availability) {
                case Set::AVAILABILITY_0:
                    break;
                case Set::AVAILABILITY_1:
                    if ($set->user_id != $this->user->id) {
                        $sets = $sets->forget($key);
                    }
                    break;
                case Set::AVAILABILITY_2:
                    if (($set->user_id != $this->user->id) && (is_null($isFollower))) {
                        $sets = $sets->forget($key);
                    }
                    break;
                case Set::AVAILABILITY_3:
                    if (($set->user_id != $this->user->id) && (is_null($isFollowee))) {
                        $sets = $sets->forget($key);
                    }
                    break;
                case Set::AVAILABILITY_4:
                    if ($set->user_id != $this->user->id) {
                        if ((is_null($isFollowee)) && (is_null($isFollower))) {
                            $sets = $sets->forget($key);
                        }
                    }
                    break;
            }
        }
        return view('sets.index', [
            'sets' => $sets,
            'user' => $this->user,
            'title' => 'Sets',
        ]);
    }

    public function create()
    {
        return view('sets.create', [
            'user' => $this->user,
            'title' => 'Create Set',
            'categories' => $this->categories,
        ]);
    }

    public function edit($id)
    {
        try {
            $set = Set::findOrFail($id);
            return view('sets.edit', [
                'set' => $set,
                'title' => 'Edit set',
                'user' => $this->user,
                'categories' => $this->categories,
            ]);
        } catch (ModelNotFoundException $e) {
            \Session::flash('flash_error', 'Edit failed. The set cannot be found.');
        }
        return redirect('/sets');
    }

    public function show($id)
    {
        try {
            $set = Set::findOrFail($id);
            return view('sets.show', [
                'set' => $set,
                'title' => 'Show set',
                'user' => $this->user,
                'categories' => $this->categories,
            ]);
        } catch (ModelNotFoundException $e) {
            \Session::flash('flash_error', 'Show failed. The set cannot be found.');
        }
        return redirect('/sets');
    }

    public function store(Request $request)
    {
        try {
            $this->validate($request, [
                'set_name' => 'required|max:255',
                'set_desc' => 'required',
                'set_image' => 'required|mimes:jpg,jpeg,gif,png',
            ]);
            $set = new Set;
            $set->assign($request);
            \Session::flash('flash_success', 'Set creation successful!');
        } catch (Exception $e) {
            \Session::flash('flash_error', 'Set creation failed.');
        }
        return redirect('/sets');
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'set_name' => 'required|max:255',
            'set_desc' => 'required',
            'set_image' => 'mimes:jpg,jpeg,gif,png',
        ]);
        $set = Set::find(intval($id));
        $set->assign($request);
        return redirect('/sets');
    }

    public function destroy(Request $request, $id)
    {
        try {
            $set = Set::findOrFail($id);
            $set->delete();
            \Session::flash('flash_success', 'Delete successful!');
        } catch (ModelNotFoundException $e) {
            \Session::flash('flash_error', 'Delete failed. The set cannot be found.');
        }
        return redirect('/sets');
    }

    public function take(Request $request)
    {
        try {
            session()->flash('setId', 0);
            session()->flash('questionIndex', 0);
            session()->flash('maxQuestions', 0);
            session()->flash('setName', $request->setName);
            session()->flash('termCount', $request->termCount);
            $set = Set::findOrFail($request->setId);
            if ($set->getTerms($request->setId, $this->user->id)) {
                session()->flash('setId', $set->id);
                return redirect('/quiz');
            }
        } catch (Exception $e) {
            dd('CATCH ERROR');
            session()->flash('flash_error', 'Set quiz failed. Please try again.');
            return redirect()->back();
        }
        dd('SET ERROR');
        return redirect('sets');
    }

    public function studySet(Request $request)
    {
        $studying = new Studying;
        $studying->addStudy($request->setId, $this->user->id);
        return redirect('/sets/' . $request->setId . '/terms/list');
    }

    public function unStudySet(Request $request)
    {
        $studying = new Studying;
        $studying->removeStudy($request->setId, $this->user->id);
        return redirect('/sets/' . $request->setId . '/terms/list');
    }

    public function recommendSet(Request $request)
    {
        $set = Set::findOrFail($request->setId);
        $set->recommended = Set::RECOMMENDED;
        $set->save();
        return redirect('/sets/' . $request->setId . '/terms/list');
    }

    public function unrecommendSet(Request $request)
    {
        $set = Set::findOrFail($request->setId);
        $set->recommended = Set::NOT_RECOMMENDED;
        $set->save();
        return redirect('/sets/' . $request->setId . '/terms/list');
    }
}

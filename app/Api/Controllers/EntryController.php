<?php

namespace Strimoid\Api\Controllers;

use Auth;
use Illuminate\Http\Request;
use Strimoid\Models\Entry;
use Strimoid\Models\EntryReply;
use Strimoid\Models\Folder;
use Strimoid\Models\Group;

class EntryController extends BaseController
{
    public function index(Request $request)
    {
        $folderName = request('folder');
        $groupName = $request->has('group') ? shadow(request('group')) : 'all';

        $className = 'Strimoid\\Models\\Folders\\' . studly_case($folderName ?: $groupName);

        if ($request->has('folder') && !class_exists('Folders\\' . studly_case($folderName))) {
            $user = $request->has('user') ? User::findOrFail(request('user')) : user();
            $folder = Folder::findUserFolderOrFail($user->getKey(), request('folder'));

            if (!$folder->public && (auth()->guest() || $user->getKey() != auth()->id())) {
                abort(404);
            }

            $builder = $folder->entries();
        } elseif (class_exists($className)) {
            $fakeGroup = new $className();
            $builder = $fakeGroup->entries();
        } else {
            $group = Group::name($groupName)->firstOrFail();
            $group->checkAccess();

            $builder = $group->entries();
        }

        $builder->with(['user', 'group', 'replies', 'replies.user'])
            ->orderBy('created_at', 'desc');

        $perPage = $request->has('per_page')
            ? between(request('per_page'), 1, 100)
            : 20;

        return $builder->paginate($perPage);
    }

    public function show(Entry $entry)
    {
        $entry->load(['user', 'group']);

        // loading of embedded relations is broken atm :(
        return array_merge($entry->toArray(), ['replies' => $entry->replies->toArray()]);
    }

    public function store(Request $request)
    {
        if ($request->has('group')) {
            $request->merge(['groupname' => request('group')]);
        }

        $this->validate($request, Entry::validationRules());

        $group = Group::name(request('group'))->firstOrFail();
        $group->checkAccess();

        if (user()->isBanned($group)) {
            return response()->json(['status' => 'error', 'error' => 'Użytkownik został zbanowany w wybranej grupie.'], 400);
        }

        if ($group->type == 'announcements' && !user()->isModerator($group)) {
            return response()->json(['status' => 'error', 'error' => 'Użytkownik nie może dodawać wpisów w tej grupie.'], 400);
        }

        $entry = new Entry();
        $entry->text = request('text');
        $entry->user()->associate(user());
        $entry->group()->associate($group);
        $entry->save();

        return response()->json(['status' => 'ok', '_id' => $entry->getKey(), 'entry' => $entry]);
    }

    public function storeReply(Request $request, Entry $entry): \Symfony\Component\HttpFoundation\Response
    {
        $this->validate($request, EntryReply::validationRules());

        if (user()->isBanned($entry->group)) {
            return response()->json([
                'status' => 'error',
                'error' => 'Użytkownik został zbanowany w wybranej grupie.',
            ], 400);
        }

        $reply = new EntryReply();
        $reply->text = request('text');
        $reply->user()->associate(user());
        $entry->replies()->save($reply);

        return response()->json(['status' => 'ok', '_id' => $reply->getKey(), 'reply' => $reply]);
    }

    public function edit(Request $request, Entry $entry): \Symfony\Component\HttpFoundation\Response
    {
        $this->validate($request, $entry->validationRules());

        if (!$entry->canEdit()) {
            abort(403, 'Access denied');
        }

        $entry->update($request->only('text'));

        return response()->json(['status' => 'ok', 'parsed' => $entry->text]);
    }

    public function remove(Entry $entry): \Symfony\Component\HttpFoundation\Response
    {
        if (!$entry->canRemove()) {
            abort(403, 'Access denied');
        }

        $entry->delete();

        return response()->json(['status' => 'ok']);
    }
}

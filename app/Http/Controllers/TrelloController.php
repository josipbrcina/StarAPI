<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;

use Trello\Client;

class TrelloController extends Controller
{
    public function createBoard(Request $request)
    {
        $name = $request->input('name');
        $predefined = $request->input('predefined');
        $trello = $this->instance();

        if (empty($name)) {
            return $this->jsonError(['Empty name field.'], 400);
        }

        if ($predefined !== 'yes') {
            $trello->boards()->create(['name' => $name, 'defaultLists' => true]);
            
            return $this->jsonSuccess([
                'created' => true,
                'board name' => $name
            ]);
        }

        $trello->boards()->create(['name' => $name, 'defaultLists' => false]);
        $boardID = $this->getBoardId($name);
        $lists   = \Config::get('trello.lists');
        $cards   = \Config::get('trello.cards');
    }

    public function createList($id, $name)
    {

        $api->boards()->lists()->create($id, [$params]);

    }
    
    public function createTicket($id , $name)
    {


        $api->cardlists()->cards()->create($id, $name, [$params]);
        
    }
    
    public function assignMember()
    {
        
    }

    public function removeMember()
    {

    }

    public function setDueDate()
    {
        
    }

    /**
     * Instantiate Trello Client
     * @return Client
     */
    private function instance()
    {
        $client = new Client();
        $client->authenticate('5c4e5563774606265cdec5c3f34c78ce', 'a1f850650b3ed5fc60c7651dc9aea134512a0fcf22265e0e9c25ef999288cdb0', Client::AUTH_URL_CLIENT_ID);

        return $client;
    }

    private function getBoardId($name = null)
    {
        $trello = $this->instance();
        $list = $trello->api('member')->boards()->all('info1x2');
        $id = '';
        foreach ($list as $board) {
            if ($board['name'] === $name) {
                $id .= $board['id'];
            }
        }
        return $id;
    }
}
<?php namespace App\Controllers;

use \App\Models\ThreadModel;
use \App\Models\KategoriModel;
use \App\Models\UserModel;
use \App\Models\ReplyModel;
use \App\Models\RatingModel;

class Thread extends BaseController
{
   public function __construct()
   {
      helper('form');
      $this->threadModel = new threadModel();
      $this->kategoriModel = new KategoriModel();
      $this->replyModel = new ReplyModel();
      $this->userModel = new UserModel();
      $this->ratingModel = new RatingModel();
      $this->validation = \Config\Services::Validation();
      $this->session = session();
   }
	public function index()
	{
      $page = 1;
      $keyword = '';

      if($this->request->getPost()) {
         $keyword = $this->request->getPost('keyword');
      }

      if($this->request->getGet()) {
         $page = $this->request->getGet('page');
      }

      $perPage = 10;
      $limit = $perPage;
      $offset = ($page - 1) * $perPage;
      $threads = $this->threadModel->getJoin($limit, $offset, $keyword);
      $total = $this->threadModel->countThread($keyword);
		return view('thread/index', [
         'threads' => $threads,
         'page' => $page,
         'perPage' => $perPage,
         'total' => $total, 
         'offset' => $offset,
         'keyword' => $keyword
      ]);
	}

   public function view()
   {
      $id = $this->request->uri->getSegment(3);
      $thread = $this->threadModel->find($id);
      $kategori = $this->kategoriModel->find($thread->id_kategori);
      $user = $this->userModel->find($thread->created_by);
      $reply = $this->replyModel->getJoin($id);

      $sum_rating = $this->ratingModel->sumRating($id);
      $count_rating = $this->ratingModel->countRating($id);
      $rating_result = 0;
      if($count_rating) {
         $rating_result = $sum_rating->star / $count_rating;
      }
      return view('thread/view', [
         'thread' => $thread,
         'kategori' => $kategori,
         'user' => $user,
         'reply' => $reply,
         'rating_result' => $rating_result
      ]);
   }

   public function create()
   {
      if($this->request->getPost()) {
         $data = $this->request->getPost();
         $this->validation->run($data, 'thread');
         $errors = $this->validation->getErrors();

         if(!$errors) {
            $thread = new \App\Entities\Thread();

            $thread->fill($data);
            $thread->created_by = session()->get('id');
            $thread->created_at = date('Y-m-d H:i:s');
            $this->threadModel->save($thread);

            // mengambil id yang baru dibuat
            $id = $this->threadModel->insertID();
            $segments = ['thread', 'view', $id];
            $this->session->setFlashdata('success', 'Input Thread Berhasil.');
            return redirect()->to(base_url($segments));
         }
         $this->session->setFlashdata('errors', $errors);
         return redirect()->to('/thread/create');
      }
      $kategori = $this->kategoriModel->findAll();
      $arrayKategori = [];
      foreach ($kategori as $kate) {
         $arrayKategori[$kate->id] = $kate->kategori;
      }
      return view('thread/create', [
         'arrayKategori' => $arrayKategori
      ]);
   }

   public function uploadImages()
   {
      $validate = $this->validate([
         'upload' => [
            'uploaded[upload]',
            'mime_in[upload,image/jpg,image/jpeg,image/png]',
            'max_size[upload,1024]'
         ]
      ]);

      if($validate) {
         $file = $this->request->getFile('upload');
         $fileName = $file->getRandomName();
         $writePath = './uploads/threads';
         $file->move($writePath, $fileName);
         $data = [
            "uploaded" => true,
            "url" => base_url('uploads/threads/'.$fileName),
         ];
      } else {
         $data = [
            "uploaded" => false,
            "error" => [
               "messages" => $file
            ]
         ];
      }
      return $this->response->setJSON($data);
   }

   public function update()
   {
      $id = $this->request->uri->getSegment(3);
      $thread = $this->threadModel->find($id);

      if($this->request->getPost()) {
         $data = $this->request->getPost();
         $this->validation->run($data, 'thread');
         $errors = $this->validation->getErrors();

         if(!$errors) {
            $thread = new \App\Entities\Thread();

            $thread->fill($data);
            $thread->updated_by = session()->get('id');
            $thread->updated_at = date('Y-m-d H:i:s');
            $this->threadModel->save($thread);

            $segments = ['thread', 'view', $id];
            $this->session->setFlashdata('success', 'Update Thread Berhasil.');
            return redirect()->to(base_url($segments));
         }
         $this->session->setFlashdata('errors', $errors);
         return redirect()->to('/thread/update/'. $id);
      }


      $kategori = $this->kategoriModel->findAll();
      $arrayKategori = [];
      foreach ($kategori as $kate) {
         $arrayKategori[$kate->id] = $kate->kategori;
      }
      return view('thread/update', [
         'arrayKategori' => $arrayKategori,
         'thread' => $thread
      ]);
   }

   public function delete()
   {
      $id = $this->request->uri->getSegment(3);
      $this->threadModel->delete($id);
      $this->session->setFlashdata('success', 'Delete Thread Berhasil.');
      return redirect()->to('/thread');
   }

   public function rate()
   {
      if($this->request->getPost()) {
         $data = $this->request->getPost();
         $rating = new \App\Entities\Rating();

         $check = $this->ratingModel->where('id_user', $data['id_user'])
                                    ->where('id_thread', $data['id_thread'])
                                    ->first();

         if($check) {
            $rating->id = $check->id;
         }

         $rating->fill($data);
         $this->ratingModel->save($rating);
         $segments = ['thread', 'view', $data['id_thread']];
         $this->session->setFlashdata('success', 'Pemberian Rating Berhasil.');
         return redirect()->to(base_url($segments));
      }
   }

	//--------------------------------------------------------------------

}

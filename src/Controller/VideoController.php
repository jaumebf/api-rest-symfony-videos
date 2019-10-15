<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Email;
use App\Entity\User;
use App\Entity\Video;
use App\Services\JwtAuth;
use Knp\Component\Pager\PaginatorInterface;

class VideoController extends AbstractController {

    private function resjson($data) {
        //Serializar datos con servicios serializer
        $json = $this->get('serializer')->serialize($data, 'json');

        //Response con httpfoundation
        $response = new Response();

        //Asignar contenido a la respuesta
        $response->setContent($json);

        //Indicar formato de respuesta
        $response->headers->set('Content-Type', 'application/json');

        //Devolver la respuesta
        return $response;
    }

    public function index() {
        return $this->json([
                    'message' => 'Welcome to your new controller!',
                    'path' => 'src/Controller/VideoController.php',
        ]);
    }

    public function create(Request $request, JwtAuth $jwt_auth, $id = null) {
        $data = [
            'status' => 'error',
            'code' => 400,
            'message' => 'El video no ha podido crearse'
        ];

        //Recoger el token
        $token = $request->headers->get('Authorization', null);

        //Comprobar si es correcto
        $authCheck = $jwt_auth->checkToken($token);

        if ($authCheck) {
            //Recoger  datos por post
            $json = $request->get('json', null);
            $params = json_decode($json);

            //Recoger el objeto del usuario identificado
            $identity = $jwt_auth->checkToken($token, true);

            //Comprobar y validar datos
            if (!empty($json)) {
                $user_id = ($identity->sub != null) ? $identity->sub : null;
                $title = (!empty($params->title)) ? $params->title : null;
                $description = (!empty($params->description)) ? $params->description : null;
                $url = (!empty($params->url)) ? $params->url : null;
            }

            if (!empty($user_id) && !empty($title)) {
                //Guardar el nuevo video en la base de datos
                $em = $this->getDoctrine()->getManager();
                $user = $this->getDoctrine()->getRepository(User::class)->findOneBy([
                    'id' => $user_id
                ]);

                if ($id == null) {
                    //Crear y guardar objeto
                    $video = new Video();
                    $video->setUser($user);
                    $video->setTitle($title);
                    $video->setDescription($description);
                    $video->setUrl($url);
                    $video->setStatus('normal');

                    $createdAt = new \DateTime('now');
                    $updatedAt = new \DateTime('now');
                    $video->setCreatedAt($createdAt);
                    $video->setUpdatedAt($updatedAt);

                    //Guardar en bd
                    $em->persist($video);
                    $em->flush();

                    $data = [
                        'status' => 'success',
                        'code' => 200,
                        'message' => 'El video se ha guardado',
                        'video' => $video
                    ];

                    //Actualizar
                } else {
                    //Buscar video
                    $video = $this->getDoctrine()->getRepository(Video::class)->findOneBy([
                        'id' => $id,
                        'user' => $identity->sub
                    ]);

                    if ($video && is_object($video)) {
                        $video->setTitle($title);
                        $video->setDescription($description);
                        $video->setUrl($url);

                        $updatedAt = new \DateTime('now');
                        $video->setUpdatedAt($updatedAt);

                        $em->persist($video);
                        $em->flush();

                        $data = [
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'El video se ha actualizado',
                            'video' => $video
                        ];
                    }
                }
            }
        }

        //Devolver una respuesta
        return $this->resjson($data);
    }

    public function videos(Request $request, JwtAuth $jwt_auth, PaginatorInterface $paginator) {
        //Recoger la cabecera de autenticación
        $token = $request->headers->get('Authorization');

        //Comprobar el token
        $authCheck = $jwt_auth->checkToken($token);

        //Si es valido
        if ($authCheck) {
            //Conseguir la identidad del usuario
            $identity = $jwt_auth->checkToken($token, true);
            $em = $this->getDoctrine()->getManager();

            //Configurar el bundle de paginacion
            $dql = "SELECT v FROM App\Entity\Video v WHERE v.user = {$identity->sub} ORDER BY v.id DESC";
            $query = $em->createQuery($dql);

            //Hacer una consulta para paginar
            $page = $request->query->getInt('page', 1);
            $items_per_page = 6;

            //Recoger el parametro page de la url
            $pagination = $paginator->paginate($query, $page, $items_per_page);
            $total = $pagination->getTotalItemCount();

            //Invocar paginación
            //Preparar array de datos para devolver
            $data = [
                'status' => 'success',
                'code' => 200,
                'total_items_count' => $total,
                'page_actual' => $page,
                'items_per_page' => $items_per_page,
                'total_pages' => ceil($total / $items_per_page),
                'videos' => $pagination,
                'user_id' => $identity->sub
            ];
        } else {
            //Si falla devolver esto:
            $data = [
                'status' => 'error',
                'code' => 404,
                'message' => 'No se pueden listar los video en este momento.'
            ];
        }


        return $this->resjson($data);
    }

    public function video(Request $request, JwtAuth $jwt_auth, $id = null) {
        //Recoger la cabecera de autenticación
        $token = $request->headers->get('Authorization');
        $authCheck = $jwt_auth->checkToken($token);

        $data = [
            'status' => 'error',
            'code' => 404,
            'message' => 'video no encontrado',
            'id' => $id
        ];

        if ($authCheck) {
            //Sacar la identidad del usuario
            $identity = $jwt_auth->checkToken($token, true);

            //Sacar el objeto del video en base el id
            $video = $this->getDoctrine()->getRepository(Video::class)->findOneBy([
                'id' => $id,
                'user' => $identity->sub
            ]);

            //Comprobar si el video existe y es propiedad del usuario identificado
            if ($video && is_object($video)) {
                $data = [
                    'status' => 'success',
                    'code' => 200,
                    'video' => $video
                ];
            }
        }

        //Devolver una respuesta       
        return $this->resjson($data);
    }

    public function remove(Request $request, JwtAuth $jwt_auth, $id = null) {
        //Recoger la cabecera de autenticación
        $token = $request->headers->get('Authorization');
        $authCheck = $jwt_auth->checkToken($token);

        $data = [
            'status' => 'error',
            'code' => 404,
            'message' => 'video no encontrado',
            'id' => $id
        ];

        if ($authCheck) {
            //Sacar la identidad del usuario
            $identity = $jwt_auth->checkToken($token, true);

            $doctrine = $this->getDoctrine();
            $em = $doctrine->getManager();

            //Sacar el objeto del video en base el id
            $video = $doctrine->getRepository(Video::class)->findOneBy([
                'id' => $id,
                'user' => $identity->sub
            ]);

            //Comprobar si el video existe y es propiedad del usuario identificado
            if ($video && is_object($video)) {
                $em->remove($video);
                $em->flush();

                $data = [
                    'status' => 'success',
                    'code' => 200,
                    'video' => $video
                ];
            }
        }

        //Devolver una respuesta       
        return $this->resjson($data);
    }

}

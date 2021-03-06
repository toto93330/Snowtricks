<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegisterType;
use App\Entity\ValidateUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class RegisterController extends AbstractController
{
    private $entityManager;
    private $session;


    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager, SessionInterface $session)
    {
        $this->entityManager = $entityManager;
        $this->session = $session;
    }

    /**
     * REGISTER SYSTEM (ROLES [ALL])
     * @Route("/register", name="register")
     */
    public function index(Request $request, UserPasswordEncoderInterface $encoder): Response
    {
        $user = new User();
        $form = $this->createForm(RegisterType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {


            $user = $form->getData();

            $userpassword = $form->getData()->getPassword();

            //1. Rename and Upload avatar
            $file = $user->getAvatar();
            $fileName = $this->generateUniqueFileName() . '.' . $file->guessExtension();
            $file->move($this->getParameter('avatar_directory'), $fileName);
            $user->setAvatar($fileName);

            //2. Defind Register Date
            $today = new \DateTime('NOW');
            $user->setRegisterAt($today);

            //3. Defind Validated user
            $user->setValidate(0);

            //4. Encode password
            $password = $encoder->encodePassword($user, $user->getPassword());
            $user->setPassword($password);

            //5. Doctrine injection for New User 
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            //6. Generate new Validation token 
            $userid = $this->entityManager->getRepository(User::class)->findOneBy(['id' => $user->getId()]);
            $validate = new ValidateUser();
            $validate->setUser($userid);
            $validate->setToken(uniqid());
            $validate->setCreatedAt($today);

            $this->entityManager->persist($validate);
            $this->entityManager->flush();

            //7. Add Flash Message  
            $this->addFlash('notify', 'Your are registered, please use validation link on email!');

            //8. Set Variable on session for send email
            $this->session->set('user-email', $user->getEmail());
            $this->session->set('user-password', $userpassword);
            $this->session->set('user-firstname', $user->getFirstname());
            $this->session->set('user-token', 'https://' . $_SERVER['HTTP_HOST'] . '/validate/account/token/' . $validate->getToken());

            //9. Send email
            return $this->redirectToRoute('mail-register');
        }


        return $this->render('register/index.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @return string
     */
    private function generateUniqueFileName()
    {
        // md5() reduces the similarity of the file names generated by
        // uniqid(), which is based on timestamps
        return md5(uniqid());
    }
}

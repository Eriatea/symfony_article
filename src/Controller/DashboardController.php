<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\User;
use App\Form\ApiTokenEditFormType;
use App\Form\ArticleFormType;
use App\Form\UserEditFormType;
use App\Repository\ArticleRepository;
use App\Service\ArticleContentProvider;
use App\Service\ArticlesCreatorProvider;
use App\Service\EditApiTokenProvider;
use App\Service\EditUserProvider;
use App\Service\FileUploader;
use App\Service\RegisterUserProvider;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/**
 * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
 * @method User|null getUser()
 * @method Article|null
 */
class DashboardController extends AbstractController
{
    /**
     * @Route("/dashboard", name="app_dashboard")
     */
    public function homepage(): Response
    {
        return $this->render('dashboard/dashboard.html.twig', []);
    }

    /**
     * @Route("/dashboard/article_detail/{slug}", name="app_dashboard_article_detail")
     */
    public function article_detail(Article $article): Response
    {
        return $this->render('dashboard/dashboard_article_detail.html.twig', [
            'article' => $article,
        ]);
    }

    /**
     * @Route("/dashboard/create_article", name="app_dashboard_create_article")
     */
    public function create_article(ArticlesCreatorProvider $articlesCreatorProvider, Request $request, FileUploader $articleFileUploader, ArticleContentProvider $articleContent): Response
    {
        $form = $this->createForm(ArticleFormType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $article = $form->getData();

            /** @var UploadedFile|null $image */
            $image = $form->get('image_filename')->getData();
            $keywords = $form->get('keywords')->getData();

            if ($image) {
                $article->setImageFilename($articleFileUploader->uploadFile($image, $article->getImageFilename()));
            }

            $plural = $form->get('plural')->getData();
            $genitive = $form->get('genitive')->getData();
            $sizeFrom = $form->get('sizeFrom')->getData();
            $sizeTo = $form->get('sizeTo')->getData();
            $theme = $form->get('theme')->getData();

            $body = $articleContent->generate_text($plural, $genitive, $keywords, $sizeFrom, $sizeTo, $theme);

            $article
                ->setDescription('Статья о ' . $keywords)
                ->setBody($body)
                ->setAuthor($this->getUser())
                ->setPublishedAt(new \DateTime());

            $articlesCreatorProvider->create($article);

            $this->addFlash('flash_message', 'Статья успешно создана');
        }

        return $this->render('dashboard/dashboard_create_article.html.twig', [
            'articleForm' => $form->createView(),
        ]);
    }

    /**
     * @Route("/dashboard/history", name="app_dashboard_history")
     */
    public function history(ArticleRepository $articleRepository, Request $request, PaginatorInterface $paginator): Response
    {
        $pagination = $paginator->paginate(
            $articleRepository->latest(),
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('dashboard/dashboard_history.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    /**
     * @Route("/dashboard/profile", name="app_dashboard_profile")
     */
    public function profile(Request $request, EditUserProvider $userService, EditApiTokenProvider $tokenProvider): Response
    {
        $formToken = $this->createForm(ApiTokenEditFormType::class, $this->getUser());
        $formToken->handleRequest($request);

        if ($formToken->isSubmitted() && $formToken->isValid()) {
            $token = $tokenProvider->editApiToken($this->getUser());

            $this->addFlash('flash_message', 'Токен успешно изменен');
        }

        $form = $this->createForm(UserEditFormType::class, $this->getUser());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('plainPassword')->getData() === $form->get('plainPasswordTwo')->getData()) {
                /** @var UserEditFormType $userModel */
                $userModel = $form->getData();

                $user = $userService->editUser($userModel);

                $this->addFlash('flash_message', 'Профиль успешно изменен');
            } else $this->addFlash('flash_message', 'Пароли не совпадают');
        }

        return $this->render('dashboard/dashboard_profile.html.twig', [
            'editTokenForm' => $formToken->createView(),
            'editUserForm' => $form->createView(),
        ]);
    }

    /**
     * @Route("/dashboard/subscription", name="app_dashboard_subscription")
     */
    public function subscription(Request $request, EditUserProvider $userService): Response
    {
        if ($request->isMethod('POST')) {
            $user = $this->getUser();

            $subscription = $request->request->get('subscription');

            $user = $user->setRoles(['ROLE_' . $subscription]);
            $user = $userService->editUser($user);

            $this->addFlash('flash_message', 'Подписка ' . $subscription . ' оформлена ');
        }

        return $this->render('dashboard/dashboard_subscription.html.twig', []);
    }
}
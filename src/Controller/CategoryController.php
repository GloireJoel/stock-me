<?php

namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use App\Service\ImgUploader;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/category')]
class CategoryController extends AbstractController
{
    #Display the table of products in GET method, check and persist the new Product in POST method
    #[Route('/', name: 'category_index', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_MEMBER', message: 'Vous devez confirmer votre email ou être connecté pour acceder à cette partie du site', statusCode: 403)]
    public function indexAndFormAddProduct(CategoryRepository $categoryRepository, Request $request, EntityManagerInterface $entityManager, ImgUploader $uploader): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check user roles //
            if (!$this->isGranted('ROLE_MANAGER')){
                throw $this->createAccessDeniedException('Vous devez être manager pour créer un produit');
            }
            // Check if product already exist by name query : display error if already exist //
            if($categoryRepository->findOneBy(['name'=>$category->getName()])){
                $this->addFlash('error', 'Catégorie déjà existante');
                return $this->redirectToRoute('category_index');
            }
            $entityManager->persist($category);
            $entityManager->flush();
            $this->addFlash('success', 'Catégorie ajoutée avec succés');
            return $this->redirectToRoute('category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('category/index.html.twig', [
            'categories' => $categoryRepository->findAll(),
            'form' => $form->createView()
        ]);
    }

    #Show the product details
    #[Route('/{id}', name: 'category_show', methods: ['GET'])]
    #[IsGranted('ROLE_MEMBER', message: 'Vous devez confirmer votre email pour acceder à cette partie du site', statusCode: 403)]
    public function show(Category $category, CategoryRepository $repository): Response
    {
        return $this->render('category/show.html.twig', [
            'category' => $category,
            'categories' => $repository->findAll()
        ]);
    }

    #Display the form for edit a product in GET method, check and persist the Product edited in POST method
    #[Route('/{id}/edit', name: 'category_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_MANAGER', message: 'Vous devez être manager pour accéder à cette partie du site', statusCode: 403)]
    public function edit(string $id, Request $request, Category $category, EntityManagerInterface $entityManager, ImgUploader $uploader): Response
    {
        $categoryRepository = $entityManager->getRepository(Category::class);
        $nameBeforePost = $categoryRepository->find($id)->getName();

        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check if product name change in field post and if is already in db //
            if($nameBeforePost !== $category->getName() && $categoryRepository->findOneBy(['name'=>$category->getName()])){
                $this->addFlash('error', 'Nom de catégorie déjà existante');
                return $this->redirectToRoute('category_edit', ['id'=>$request->get('id')]);
            }


            $entityManager->flush();
            $this->addFlash('success', 'catégorie éditée avec succés');
            return $this->redirectToRoute('category_show', ['id'=> $request->get('id')], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('category/edit.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }


    #[Route('/{id}', name: 'category_delete', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER', message: 'Vous devez être manager ou administrateur pour supprimer un article', statusCode: 403)]
    public function delete(Request $request, Category $category): Response
    {
        if ($this->isCsrfTokenValid('delete'.$category->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($category);
            $entityManager->flush();
        }

        return $this->redirectToRoute('category_index', [], Response::HTTP_SEE_OTHER);
    }

}
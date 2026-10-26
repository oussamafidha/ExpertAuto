<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Entity\User;
use App\Kernel;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;

$kernel = new Kernel('dev', true);
$kernel->boot();

$em = $kernel->getContainer()->get('doctrine')->getManager();
$hasher = new NativePasswordHasher();

$users = [
    ['admin@expertauto.test', 'Admin User', [User::ROLE_ADMIN], 'AdminPass123!'],
    ['mechanic@expertauto.test', 'Mechanic User', [User::ROLE_MECHANIC], 'MechanicPass123!'],
    ['storekeeper@expertauto.test', 'Storekeeper User', [User::ROLE_STOREKEEPER], 'StorekeeperPass123!'],
    ['client@expertauto.test', 'Client User', [User::ROLE_CLIENT], 'ClientPass123!'],
];

foreach ($users as [$email, $name, $roles, $plainPassword]) {
    $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
    if (!$user) {
        $user = new User();
        $user->setEmail($email)
            ->setFullName($name)
            ->setRoles($roles)
            ->setPassword($hasher->hash($plainPassword));

        $em->persist($user);
        echo "CREATED: {$email}\n";
    } else {
        echo "EXISTS: {$email}\n";
    }
}

$em->flush();

echo "---\n";
foreach ($em->getRepository(User::class)->findAll() as $user) {
    echo $user->getEmail() . ' | ' . implode(', ', $user->getRoles()) . PHP_EOL;
}
